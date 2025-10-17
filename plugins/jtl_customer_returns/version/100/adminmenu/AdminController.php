<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\adminmenu;

use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\jtl_customer_returns\Repositories\RMARepository;
use Plugin\jtl_customer_returns\Repositories\RMAItemRepository;
use Plugin\jtl_customer_returns\Repositories\RMAHistoryRepository;
use Plugin\jtl_customer_returns\Services\ReturnRequestService;
use Plugin\jtl_customer_returns\Services\NotificationService;
use Plugin\jtl_customer_returns\Services\ShippingLabelService;
use Plugin\jtl_customer_returns\Helper\RMAItems;

/**
 * Admin Controller - Backend-Verwaltung für Retouren
 */
class AdminController
{
    private RMARepository $rmaRepo;
    private RMAItemRepository $itemRepo;
    private RMAHistoryRepository $historyRepo;
    private ReturnRequestService $returnService;
    private NotificationService $notificationService;
    private ShippingLabelService $labelService;
    private JTLSmarty $smarty;
    
    public function __construct()
    {
        $this->rmaRepo = new RMARepository();
        $this->itemRepo = new RMAItemRepository();
        $this->historyRepo = new RMAHistoryRepository();
        $this->returnService = new ReturnRequestService();
        $this->notificationService = new NotificationService();
        $this->labelService = new ShippingLabelService();
        $this->smarty = Shop::Smarty();
    }
    
    /**
     * Retouren-Übersicht
     *
     * @return string HTML
     */
    public function actionOverview(): string
    {
        $filter = $_GET['filter'] ?? 'all';
        $search = $_GET['search'] ?? '';
        
        // Retouren laden
        $rmas = [];
        
        if ($filter === 'all') {
            $rmas = $this->rmaRepo->findAll(500, 0);
        } else {
            $status = (int)$filter;
            $rmas = $this->rmaRepo->getByStatus($status, 500);
        }
        
        // Suchfilter
        if (!empty($search)) {
            $rmas = array_filter($rmas, function($rma) use ($search) {
                return stripos($rma->getRmaNr(), $search) !== false;
            });
        }
        
        // Items für jede RMA laden
        $rmasWithItems = [];
        foreach ($rmas as $rma) {
            $items = $this->itemRepo->getByRmaID($rma->getId());
            $rmasWithItems[] = [
                'rma' => $rma,
                'items' => $items,
                'itemCount' => count($items),
                'totalQty' => RMAItems::getTotalQuantity($items)
            ];
        }
        
        // Statistiken
        $stats = [
            'total' => $this->rmaRepo->count(),
            'open' => $this->rmaRepo->count('status = 0'),
            'in_progress' => $this->rmaRepo->count('status = 1'),
            'accepted' => $this->rmaRepo->count('status = 2'),
            'completed' => $this->rmaRepo->count('status = 3'),
            'rejected' => $this->rmaRepo->count('status = 4'),
        ];
        
        $this->smarty->assign('rmas', $rmasWithItems);
        $this->smarty->assign('filter', $filter);
        $this->smarty->assign('search', $search);
        $this->smarty->assign('stats', $stats);
        
        return $this->smarty->fetch(
            PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/version/100/adminmenu/templates/overview.tpl'
        );
    }
    
    /**
     * Retouren-Detail (Bearbeiten)
     *
     * @return string HTML
     */
    public function actionEdit(): string
    {
        $rmaID = (int)($_GET['rmaID'] ?? 0);
        
        if ($rmaID === 0) {
            return '<div class="alert alert-danger">Ungültige RMA-ID</div>';
        }
        
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            return '<div class="alert alert-danger">RMA nicht gefunden</div>';
        }
        
        // POST-Request verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleEditFormSubmit($rma);
        }
        
        // Items laden
        $items = $this->itemRepo->getByRmaID($rmaID);
        
        // Historie laden
        $history = $this->historyRepo->getByRmaID($rmaID);
        
        // Kundendaten laden
        $customer = $this->getCustomerData($rma->getCustomerID());
        
        // Bestelldaten laden
        $order = $this->getOrderData($rma->getOrderID());
        
        $this->smarty->assign('rma', $rma);
        $this->smarty->assign('items', $items);
        $this->smarty->assign('history', $history);
        $this->smarty->assign('customer', $customer);
        $this->smarty->assign('order', $order);
        $this->smarty->assign('statusOptions', $this->getStatusOptions());
        
        return $this->smarty->fetch(
            PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/version/100/adminmenu/templates/edit.tpl'
        );
    }
    
    /**
     * Statistiken
     *
     * @return string HTML
     */
    public function actionStatistics(): string
    {
        // Zeitraum-Filter
        $period = $_GET['period'] ?? '30days';
        
        $dateFrom = $this->getDateFromPeriod($period);
        $dateTo = date('Y-m-d 23:59:59');
        
        // Statistiken berechnen
        $stats = [
            'total_rmas' => $this->getRMACount($dateFrom, $dateTo),
            'total_items' => $this->getItemCount($dateFrom, $dateTo),
            'total_refund' => $this->getTotalRefund($dateFrom, $dateTo),
            'avg_processing_time' => $this->getAvgProcessingTime($dateFrom, $dateTo),
        ];
        
        // Status-Verteilung
        $statusDistribution = [
            ['status' => 'Offen', 'count' => $this->getRMACountByStatus(0, $dateFrom, $dateTo)],
            ['status' => 'In Bearbeitung', 'count' => $this->getRMACountByStatus(1, $dateFrom, $dateTo)],
            ['status' => 'Akzeptiert', 'count' => $this->getRMACountByStatus(2, $dateFrom, $dateTo)],
            ['status' => 'Abgeschlossen', 'count' => $this->getRMACountByStatus(3, $dateFrom, $dateTo)],
            ['status' => 'Abgelehnt', 'count' => $this->getRMACountByStatus(4, $dateFrom, $dateTo)],
        ];
        
        // Top Retourengründe
        $topReasons = $this->getTopReturnReasons($dateFrom, $dateTo, 10);
        
        // Timeline (letzte 30 Tage)
        $timeline = $this->getRMATimeline(30);
        
        $this->smarty->assign('period', $period);
        $this->smarty->assign('dateFrom', $dateFrom);
        $this->smarty->assign('dateTo', $dateTo);
        $this->smarty->assign('stats', $stats);
        $this->smarty->assign('statusDistribution', $statusDistribution);
        $this->smarty->assign('topReasons', $topReasons);
        $this->smarty->assign('timeline', $timeline);
        
        return $this->smarty->fetch(
            PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/version/100/adminmenu/templates/statistics.tpl'
        );
    }
    
    /**
     * Edit-Form verarbeiten
     *
     * @param RMADomainObject $rma
     * @return string HTML
     */
    private function handleEditFormSubmit($rma): string
    {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'update_status':
                    $newStatus = (int)$_POST['status'];
                    $comment = $_POST['comment'] ?? '';
                    
                    $this->returnService->updateStatus($rma->getId(), $newStatus, $comment);
                    
                    // E-Mail senden
                    if (isset($_POST['send_email']) && $_POST['send_email'] === '1') {
                        $this->notificationService->sendStatusUpdate($rma->getId(), $comment);
                    }
                    
                    $message = '<div class="alert alert-success">Status erfolgreich aktualisiert</div>';
                    break;
                    
                case 'create_label':
                    $result = $this->labelService->createLabel($rma->getId());
                    
                    if ($result['success']) {
                        $message = '<div class="alert alert-success">Versandlabel erstellt</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Fehler: ' . $result['error'] . '</div>';
                    }
                    break;
                    
                case 'send_email':
                    $emailType = $_POST['email_type'] ?? '';
                    
                    if ($emailType === 'status_update') {
                        $this->notificationService->sendStatusUpdate($rma->getId());
                    }
                    
                    $message = '<div class="alert alert-success">E-Mail versendet</div>';
                    break;
                    
                default:
                    $message = '<div class="alert alert-warning">Unbekannte Aktion</div>';
            }
        } catch (\Exception $e) {
            $message = '<div class="alert alert-danger">Fehler: ' . $e->getMessage() . '</div>';
        }
        
        // Seite neu laden mit Nachricht
        $this->smarty->assign('message', $message);
        return $this->actionEdit();
    }
    
    /**
     * Kundendaten laden
     *
     * @param int $customerID
     * @return array
     */
    private function getCustomerData(int $customerID): array
    {
        $db = Shop::Container()->getDB();
        
        $customer = $db->queryPrepared(
            "SELECT kKunde, cVorname, cNachname, cMail, cTel, cPLZ, cOrt 
             FROM tkunde 
             WHERE kKunde = :customerID",
            ['customerID' => $customerID],
            1
        );
        
        return [
            'id' => $customer->kKunde ?? 0,
            'fullName' => trim(($customer->cVorname ?? '') . ' ' . ($customer->cNachname ?? '')),
            'email' => $customer->cMail ?? '',
            'phone' => $customer->cTel ?? '',
            'city' => $customer->cOrt ?? '',
            'zip' => $customer->cPLZ ?? ''
        ];
    }
    
    /**
     * Bestelldaten laden
     *
     * @param int $orderID
     * @return array
     */
    private function getOrderData(int $orderID): array
    {
        $db = Shop::Container()->getDB();
        
        $order = $db->queryPrepared(
            "SELECT cBestellNr, dErstellt, dVersandt, fGesamtsumme 
             FROM tbestellung 
             WHERE kBestellung = :orderID",
            ['orderID' => $orderID],
            1
        );
        
        return [
            'orderNo' => $order->cBestellNr ?? '',
            'orderDate' => $order->dErstellt ?? '',
            'shippedDate' => $order->dVersandt ?? '',
            'total' => (float)($order->fGesamtsumme ?? 0)
        ];
    }
    
    /**
     * Status-Optionen für Dropdown
     *
     * @return array
     */
    private function getStatusOptions(): array
    {
        return [
            ['value' => 0, 'label' => 'Offen'],
            ['value' => 1, 'label' => 'In Bearbeitung'],
            ['value' => 2, 'label' => 'Akzeptiert'],
            ['value' => 3, 'label' => 'Abgeschlossen'],
            ['value' => 4, 'label' => 'Abgelehnt'],
        ];
    }
    
    /**
     * Datum aus Period-String berechnen
     *
     * @param string $period
     * @return string
     */
    private function getDateFromPeriod(string $period): string
    {
        switch ($period) {
            case '7days':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case '90days':
                return date('Y-m-d 00:00:00', strtotime('-90 days'));
            case '1year':
                return date('Y-m-d 00:00:00', strtotime('-1 year'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
    }
    
    /**
     * RMA-Anzahl im Zeitraum
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return int
     */
    private function getRMACount(string $dateFrom, string $dateTo): int
    {
        return $this->rmaRepo->count(
            'createDate BETWEEN :from AND :to',
            ['from' => $dateFrom, 'to' => $dateTo]
        );
    }
    
    /**
     * Item-Anzahl im Zeitraum
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return int
     */
    private function getItemCount(string $dateFrom, string $dateTo): int
    {
        $db = Shop::Container()->getDB();
        
        $result = $db->queryPrepared(
            "SELECT COUNT(*) AS cnt 
             FROM rma_items ri
             INNER JOIN rma r ON ri.rmaID = r.id
             WHERE r.createDate BETWEEN :from AND :to",
            ['from' => $dateFrom, 'to' => $dateTo],
            1
        );
        
        return (int)($result->cnt ?? 0);
    }
    
    /**
     * Gesamtrückerstattung im Zeitraum
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return float
     */
    private function getTotalRefund(string $dateFrom, string $dateTo): float
    {
        $db = Shop::Container()->getDB();
        
        $result = $db->queryPrepared(
            "SELECT SUM(totalGross) AS total 
             FROM rma 
             WHERE createDate BETWEEN :from AND :to",
            ['from' => $dateFrom, 'to' => $dateTo],
            1
        );
        
        return (float)($result->total ?? 0);
    }
    
    /**
     * Durchschnittliche Bearbeitungszeit
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return float Tage
     */
    private function getAvgProcessingTime(string $dateFrom, string $dateTo): float
    {
        $db = Shop::Container()->getDB();
        
        $result = $db->queryPrepared(
            "SELECT AVG(DATEDIFF(updateDate, createDate)) AS avg_days 
             FROM rma 
             WHERE createDate BETWEEN :from AND :to 
             AND status = 3",
            ['from' => $dateFrom, 'to' => $dateTo],
            1
        );
        
        return round((float)($result->avg_days ?? 0), 1);
    }
    
    /**
     * RMA-Anzahl nach Status
     *
     * @param int $status
     * @param string $dateFrom
     * @param string $dateTo
     * @return int
     */
    private function getRMACountByStatus(int $status, string $dateFrom, string $dateTo): int
    {
        return $this->rmaRepo->count(
            'status = :status AND createDate BETWEEN :from AND :to',
            ['status' => $status, 'from' => $dateFrom, 'to' => $dateTo]
        );
    }
    
    /**
     * Top Retourengründe
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @param int $limit
     * @return array
     */
    private function getTopReturnReasons(string $dateFrom, string $dateTo, int $limit): array
    {
        $db = Shop::Container()->getDB();
        
        $results = $db->queryPrepared(
            "SELECT rr.reason, COUNT(*) AS cnt
             FROM rma_items ri
             INNER JOIN rma r ON ri.rmaID = r.id
             INNER JOIN rma_reasons rr ON ri.reasonID = rr.id
             WHERE r.createDate BETWEEN :from AND :to
             GROUP BY ri.reasonID
             ORDER BY cnt DESC
             LIMIT :limit",
            ['from' => $dateFrom, 'to' => $dateTo, 'limit' => $limit],
            2
        );
        
        return array_map(
            fn($row) => ['reason' => $row->reason, 'count' => (int)$row->cnt],
            $results
        );
    }
    
    /**
     * RMA-Timeline (Anzahl pro Tag)
     *
     * @param int $days
     * @return array
     */
    private function getRMATimeline(int $days): array
    {
        $db = Shop::Container()->getDB();
        
        $results = $db->queryPrepared(
            "SELECT DATE(createDate) AS date, COUNT(*) AS cnt
             FROM rma
             WHERE createDate >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(createDate)
             ORDER BY date ASC",
            ['days' => $days],
            2
        );
        
        return array_map(
            fn($row) => ['date' => $row->date, 'count' => (int)$row->cnt],
            $results
        );
    }
}
