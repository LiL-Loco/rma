<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Services;

use JTL\Shop;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use Plugin\jtl_customer_returns\DomainObjects\RMADomainObject;
use Plugin\jtl_customer_returns\Repositories\RMARepository;
use Plugin\jtl_customer_returns\Repositories\RMAItemRepository;
use Plugin\jtl_customer_returns\Helper\RMAHistoryEvents;

/**
 * Notification Service - E-Mail-Versand
 */
class NotificationService
{
    private RMARepository $rmaRepo;
    private RMAItemRepository $itemRepo;
    private RMAHistoryService $historyService;
    
    public function __construct()
    {
        $this->rmaRepo = new RMARepository();
        $this->itemRepo = new RMAItemRepository();
        $this->historyService = new RMAHistoryService();
    }
    
    /**
     * Bestätigungs-E-Mail senden
     *
     * @param int $rmaID
     * @return bool
     */
    public function sendReturnConfirmation(int $rmaID): bool
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            return false;
        }
        
        $customer = $this->getCustomerData($rma->getCustomerID());
        $items = $this->itemRepo->getByRmaID($rmaID);
        
        $data = [
            'rma_number' => $rma->getRmaNr(),
            'customer_name' => $customer['fullName'],
            'return_items' => $this->formatItemsForEmail($items),
            'return_date' => date('d.m.Y', strtotime($rma->getCreateDate())),
            'total_amount' => number_format($rma->getTotalGross(), 2, ',', '.') . ' €',
        ];
        
        $sent = $this->sendMail(
            $customer['email'],
            'jtl_customer_returns_email_confirmation',
            $data
        );
        
        if ($sent) {
            $this->historyService->addEvent(
                $rmaID,
                RMAHistoryEvents::EMAIL_CONFIRMATION_SENT,
                ['recipient' => $customer['email']]
            );
        }
        
        return $sent;
    }
    
    /**
     * Status-Update-E-Mail senden
     *
     * @param int $rmaID
     * @param string|null $customMessage
     * @return bool
     */
    public function sendStatusUpdate(int $rmaID, ?string $customMessage = null): bool
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            return false;
        }
        
        $customer = $this->getCustomerData($rma->getCustomerID());
        
        $data = [
            'rma_number' => $rma->getRmaNr(),
            'customer_name' => $customer['fullName'],
            'status' => $rma->getStatusText(),
            'status_color' => $rma->getStatusColor(),
            'message' => $customMessage ?? $this->getDefaultStatusMessage($rma->getStatus()),
            'update_date' => date('d.m.Y H:i', strtotime($rma->getUpdateDate())),
        ];
        
        $sent = $this->sendMail(
            $customer['email'],
            'jtl_customer_returns_email_status_update',
            $data
        );
        
        if ($sent) {
            $this->historyService->addEvent(
                $rmaID,
                RMAHistoryEvents::EMAIL_STATUS_UPDATE_SENT,
                ['recipient' => $customer['email'], 'status' => $rma->getStatus()]
            );
        }
        
        return $sent;
    }
    
    /**
     * Gutschein-E-Mail senden
     *
     * @param int $rmaID
     * @param string $voucherCode
     * @param float $voucherAmount
     * @return bool
     */
    public function sendVoucherNotification(int $rmaID, string $voucherCode, float $voucherAmount): bool
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            return false;
        }
        
        $customer = $this->getCustomerData($rma->getCustomerID());
        
        $data = [
            'rma_number' => $rma->getRmaNr(),
            'customer_name' => $customer['fullName'],
            'voucher_code' => $voucherCode,
            'voucher_amount' => number_format($voucherAmount, 2, ',', '.') . ' €',
            'valid_until' => date('d.m.Y', strtotime('+1 year')),
        ];
        
        $sent = $this->sendMail(
            $customer['email'],
            'jtl_customer_returns_email_voucher',
            $data
        );
        
        if ($sent) {
            $this->historyService->addEvent(
                $rmaID,
                RMAHistoryEvents::EMAIL_VOUCHER_SENT,
                [
                    'recipient' => $customer['email'],
                    'voucherCode' => $voucherCode,
                    'amount' => $voucherAmount
                ]
            );
        }
        
        return $sent;
    }
    
    /**
     * Rückzahlungs-E-Mail senden
     *
     * @param int $rmaID
     * @param float $refundAmount
     * @param string $refundMethod (z.B. "Banküberweisung", "PayPal")
     * @return bool
     */
    public function sendRefundNotification(int $rmaID, float $refundAmount, string $refundMethod): bool
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            return false;
        }
        
        $customer = $this->getCustomerData($rma->getCustomerID());
        
        $data = [
            'rma_number' => $rma->getRmaNr(),
            'customer_name' => $customer['fullName'],
            'refund_amount' => number_format($refundAmount, 2, ',', '.') . ' €',
            'refund_method' => $refundMethod,
            'refund_date' => date('d.m.Y'),
        ];
        
        $sent = $this->sendMail(
            $customer['email'],
            'jtl_customer_returns_email_refund',
            $data
        );
        
        if ($sent) {
            $this->historyService->addEvent(
                $rmaID,
                RMAHistoryEvents::EMAIL_REFUND_SENT,
                [
                    'recipient' => $customer['email'],
                    'amount' => $refundAmount,
                    'method' => $refundMethod
                ]
            );
        }
        
        return $sent;
    }
    
    /**
     * E-Mail versenden
     *
     * @param string $recipient
     * @param string $templateKey
     * @param array $data
     * @return bool
     */
    private function sendMail(string $recipient, string $templateKey, array $data): bool
    {
        try {
            $mail = new Mail();
            
            // Template laden (aus info.xml definiert)
            $template = Shop::Container()->getTemplateService()->load($templateKey);
            
            if (!$template) {
                Shop::Container()->getLogService()->error(
                    "E-Mail-Template nicht gefunden: {$templateKey}"
                );
                return false;
            }
            
            // Variablen ersetzen
            $subject = $this->replaceVariables($template->getSubject(), $data);
            $body = $this->replaceVariables($template->getContentHtml(), $data);
            
            $mail->setToMail($recipient);
            $mail->setSubject($subject);
            $mail->setBodyHTML($body);
            
            $mailer = Shop::Container()->get(Mailer::class);
            $mailer->send($mail);
            
            return true;
            
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error(
                "E-Mail-Versand fehlgeschlagen: {$e->getMessage()}"
            );
            return false;
        }
    }
    
    /**
     * Platzhalter in Template ersetzen
     *
     * @param string $text
     * @param array $data
     * @return string
     */
    private function replaceVariables(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $text = str_replace("{{$key}}", (string)$value, $text);
        }
        
        return $text;
    }
    
    /**
     * Kundendaten laden
     *
     * @param int $customerID
     * @return array{fullName: string, email: string}
     */
    private function getCustomerData(int $customerID): array
    {
        $db = Shop::Container()->getDB();
        
        $customer = $db->queryPrepared(
            "SELECT cVorname, cNachname, cMail FROM tkunde WHERE kKunde = :customerID",
            ['customerID' => $customerID],
            1
        );
        
        return [
            'fullName' => trim(($customer->cVorname ?? '') . ' ' . ($customer->cNachname ?? '')),
            'email' => $customer->cMail ?? ''
        ];
    }
    
    /**
     * Items für E-Mail formatieren
     *
     * @param array $items
     * @return string
     */
    private function formatItemsForEmail(array $items): string
    {
        $formatted = [];
        
        foreach ($items as $item) {
            $formatted[] = sprintf(
                '%dx Artikel #%d (Grund: %s)',
                $item->getQuantity(),
                $item->getProductID(),
                $item->getReasonID()
            );
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Standard-Status-Nachricht generieren
     *
     * @param int $status
     * @return string
     */
    private function getDefaultStatusMessage(int $status): string
    {
        $messages = [
            RMADomainObject::STATUS_OPEN => 'Ihre Retoure wurde erfolgreich angelegt.',
            RMADomainObject::STATUS_IN_PROGRESS => 'Ihre Retoure wird bearbeitet.',
            RMADomainObject::STATUS_ACCEPTED => 'Ihre Retoure wurde akzeptiert.',
            RMADomainObject::STATUS_COMPLETED => 'Ihre Retoure wurde abgeschlossen.',
            RMADomainObject::STATUS_REJECTED => 'Ihre Retoure wurde abgelehnt.',
        ];
        
        return $messages[$status] ?? 'Der Status Ihrer Retoure hat sich geändert.';
    }
}
