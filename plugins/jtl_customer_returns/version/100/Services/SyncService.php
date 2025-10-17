<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Services;

use JTL\Shop;
use Plugin\jtl_customer_returns\DomainObjects\RMADomainObject;
use Plugin\jtl_customer_returns\DomainObjects\dbeS\RMASyncObject;
use Plugin\jtl_customer_returns\Repositories\RMARepository;
use Plugin\jtl_customer_returns\Repositories\RMAItemRepository;
use Plugin\jtl_customer_returns\Repositories\RMAReturnAddressRepository;
use Plugin\jtl_customer_returns\Helper\RMAHistoryEvents;

/**
 * Sync Service - Wawi-Synchronisation
 */
class SyncService
{
    private RMARepository $rmaRepo;
    private RMAItemRepository $itemRepo;
    private RMAReturnAddressRepository $addressRepo;
    private RMAHistoryService $historyService;
    
    public function __construct()
    {
        $this->rmaRepo = new RMARepository();
        $this->itemRepo = new RMAItemRepository();
        $this->addressRepo = new RMAReturnAddressRepository();
        $this->historyService = new RMAHistoryService();
    }
    
    /**
     * RMA an Wawi senden
     *
     * @param RMADomainObject $rma
     * @param int $maxRetries
     * @return bool
     */
    public function syncToWawi(RMADomainObject $rma, int $maxRetries = 3): bool
    {
        try {
            // Sync-Object erstellen
            $syncObject = $this->createSyncObject($rma);
            
            // XML generieren
            $xml = $syncObject->toXML();
            
            // An dbeS-Queue senden
            $sent = $this->sendToDBeSQueue($xml, $rma->getId());
            
            if ($sent) {
                // Als synchronisiert markieren
                $rma->setSynced(1);
                $rma->setUpdateDate(date('Y-m-d H:i:s'));
                $this->rmaRepo->save($rma);
                
                // History-Eintrag
                $this->historyService->addEvent(
                    $rma->getId(),
                    RMAHistoryEvents::WAWI_SYNCED,
                    ['timestamp' => time()]
                );
                
                Shop::Container()->getLogService()->info(
                    "RMA #{$rma->getId()} erfolgreich an Wawi gesendet"
                );
                
                return true;
            } else {
                throw new \RuntimeException('dbeS-Queue-Übertragung fehlgeschlagen');
            }
            
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error(
                "Wawi-Sync für RMA #{$rma->getId()} fehlgeschlagen: {$e->getMessage()}"
            );
            
            // Retry-Logik
            if ($maxRetries > 0) {
                sleep(5);
                return $this->syncToWawi($rma, $maxRetries - 1);
            }
            
            return false;
        }
    }
    
    /**
     * Alle unsynchronisierten RMAs synchronisieren
     *
     * @param int $limit
     * @return array{success: int, failed: int}
     */
    public function syncPendingRMAs(int $limit = 100): array
    {
        $pending = $this->rmaRepo->getUnsynchronized($limit);
        
        $success = 0;
        $failed = 0;
        
        foreach ($pending as $rma) {
            if ($this->syncToWawi($rma)) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    /**
     * Wawi-Update empfangen (von Wawi → Shop)
     *
     * @param int $rmaID
     * @param array $updateData {status?, wawiID?, items?: []}
     * @return void
     */
    public function handleWawiUpdate(int $rmaID, array $updateData): void
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            Shop::Container()->getLogService()->warning(
                "Wawi-Update für unbekannte RMA #{$rmaID}"
            );
            return;
        }
        
        $changed = false;
        
        // Status aktualisieren
        if (isset($updateData['status']) && $updateData['status'] !== $rma->getStatus()) {
            $rma->setStatus((int)$updateData['status']);
            $changed = true;
        }
        
        // Wawi-ID setzen
        if (isset($updateData['wawiID']) && $updateData['wawiID'] !== $rma->getWawiID()) {
            $rma->setWawiID((int)$updateData['wawiID']);
            $changed = true;
        }
        
        // Item-Status aktualisieren
        if (isset($updateData['items'])) {
            foreach ($updateData['items'] as $itemUpdate) {
                $item = $this->itemRepo->find($itemUpdate['id']);
                if ($item && isset($itemUpdate['itemStatus'])) {
                    $item->setItemStatus((int)$itemUpdate['itemStatus']);
                    if (isset($itemUpdate['refundAmount'])) {
                        $item->setRefundAmount((float)$itemUpdate['refundAmount']);
                    }
                    $this->itemRepo->save($item);
                }
            }
        }
        
        if ($changed) {
            $rma->setUpdateDate(date('Y-m-d H:i:s'));
            $this->rmaRepo->save($rma);
            
            // History-Eintrag
            $this->historyService->addEvent(
                $rmaID,
                RMAHistoryEvents::WAWI_UPDATE_RECEIVED,
                $updateData
            );
        }
    }
    
    /**
     * Sync-Object erstellen
     *
     * @param RMADomainObject $rma
     * @return RMASyncObject
     */
    private function createSyncObject(RMADomainObject $rma): RMASyncObject
    {
        // Items laden
        $items = $this->itemRepo->getByRmaID($rma->getId());
        
        // Rücksendeadresse laden
        $address = null;
        if ($rma->getReturnAddressID()) {
            $address = $this->addressRepo->find($rma->getReturnAddressID());
        }
        
        // Sync-Object erstellen
        $syncObject = RMASyncObject::fromDomainObject($rma);
        
        // Items hinzufügen
        foreach ($items as $item) {
            $syncObject->addItem($item);
        }
        
        // Adresse hinzufügen
        if ($address) {
            $syncObject->setAddress($address);
        }
        
        return $syncObject;
    }
    
    /**
     * XML an dbeS-Queue senden
     *
     * @param string $xml
     * @param int $rmaID
     * @return bool
     */
    private function sendToDBeSQueue(string $xml, int $rmaID): bool
    {
        try {
            $db = Shop::Container()->getDB();
            
            // In dbeS-Queue eintragen
            $db->queryPrepared(
                "INSERT INTO dbes_queue (type, xml, created_at, status) 
                 VALUES ('rma', :xml, NOW(), 'pending')",
                ['xml' => $xml]
            );
            
            return true;
            
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error(
                "dbeS-Queue Insert fehlgeschlagen: {$e->getMessage()}"
            );
            return false;
        }
    }
}
