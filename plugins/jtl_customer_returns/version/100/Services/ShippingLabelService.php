<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Services;

use JTL\Shop;
use Plugin\jtl_customer_returns\DomainObjects\RMADomainObject;
use Plugin\jtl_customer_returns\Repositories\RMARepository;
use Plugin\jtl_customer_returns\Helper\RMAHistoryEvents;

/**
 * Shipping Label Service - Versandlabel-Erstellung
 */
class ShippingLabelService
{
    private RMARepository $rmaRepo;
    private RMAHistoryService $historyService;
    
    public function __construct()
    {
        $this->rmaRepo = new RMARepository();
        $this->historyService = new RMAHistoryService();
    }
    
    /**
     * Versandlabel erstellen
     *
     * @param int $rmaID
     * @return array{success: bool, labelPath?: string, error?: string}
     */
    public function createLabel(int $rmaID): array
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            return ['success' => false, 'error' => 'RMA nicht gefunden'];
        }
        
        // Versanddienstleister aus Config laden
        $provider = Shop::Container()->getConfigService()->get(
            'jtl_customer_returns_shipping_provider',
            'dhl'
        );
        
        try {
            switch ($provider) {
                case 'dhl':
                    $labelPath = $this->createDHLLabel($rma);
                    break;
                case 'dpd':
                    $labelPath = $this->createDPDLabel($rma);
                    break;
                case 'ups':
                    $labelPath = $this->createUPSLabel($rma);
                    break;
                default:
                    return ['success' => false, 'error' => 'Unbekannter Versanddienstleister'];
            }
            
            // Label-Pfad speichern
            $rma->setLabelPath($labelPath);
            $rma->setUpdateDate(date('Y-m-d H:i:s'));
            $this->rmaRepo->save($rma);
            
            // History-Eintrag
            $this->historyService->addEvent(
                $rmaID,
                RMAHistoryEvents::LABEL_CREATED,
                ['provider' => $provider, 'labelPath' => $labelPath]
            );
            
            return ['success' => true, 'labelPath' => $labelPath];
            
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error(
                "Label-Erstellung fehlgeschlagen (RMA #{$rmaID}): {$e->getMessage()}"
            );
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * DHL-Retourenlabel erstellen
     *
     * @param RMADomainObject $rma
     * @return string Pfad zum PDF
     * @throws \RuntimeException
     */
    private function createDHLLabel(RMADomainObject $rma): string
    {
        // TODO: DHL API Integration
        // - Authentifizierung mit API-Key
        // - Retourenschein-Anforderung
        // - PDF speichern
        
        // Placeholder: Mock-Label erstellen
        $labelDir = PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/labels/';
        
        if (!is_dir($labelDir)) {
            mkdir($labelDir, 0755, true);
        }
        
        $filename = "dhl_label_{$rma->getRmaNr()}.pdf";
        $labelPath = $labelDir . $filename;
        
        // Mock-PDF (für Entwicklung)
        file_put_contents($labelPath, "DHL Mock Label for RMA {$rma->getRmaNr()}");
        
        Shop::Container()->getLogService()->info(
            "DHL-Label erstellt: {$labelPath}"
        );
        
        return $labelPath;
    }
    
    /**
     * DPD-Retourenlabel erstellen
     *
     * @param RMADomainObject $rma
     * @return string Pfad zum PDF
     * @throws \RuntimeException
     */
    private function createDPDLabel(RMADomainObject $rma): string
    {
        // TODO: DPD API Integration
        
        $labelDir = PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/labels/';
        
        if (!is_dir($labelDir)) {
            mkdir($labelDir, 0755, true);
        }
        
        $filename = "dpd_label_{$rma->getRmaNr()}.pdf";
        $labelPath = $labelDir . $filename;
        
        file_put_contents($labelPath, "DPD Mock Label for RMA {$rma->getRmaNr()}");
        
        Shop::Container()->getLogService()->info(
            "DPD-Label erstellt: {$labelPath}"
        );
        
        return $labelPath;
    }
    
    /**
     * UPS-Retourenlabel erstellen
     *
     * @param RMADomainObject $rma
     * @return string Pfad zum PDF
     * @throws \RuntimeException
     */
    private function createUPSLabel(RMADomainObject $rma): string
    {
        // TODO: UPS API Integration
        
        $labelDir = PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/labels/';
        
        if (!is_dir($labelDir)) {
            mkdir($labelDir, 0755, true);
        }
        
        $filename = "ups_label_{$rma->getRmaNr()}.pdf";
        $labelPath = $labelDir . $filename;
        
        file_put_contents($labelPath, "UPS Mock Label for RMA {$rma->getRmaNr()}");
        
        Shop::Container()->getLogService()->info(
            "UPS-Label erstellt: {$labelPath}"
        );
        
        return $labelPath;
    }
    
    /**
     * Label-PDF abrufen
     *
     * @param int $rmaID
     * @return string|null Pfad zum PDF oder null
     */
    public function getLabel(int $rmaID): ?string
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma || !$rma->getLabelPath()) {
            return null;
        }
        
        $labelPath = $rma->getLabelPath();
        
        if (file_exists($labelPath)) {
            return $labelPath;
        }
        
        return null;
    }
}
