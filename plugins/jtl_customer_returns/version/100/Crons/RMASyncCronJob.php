<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Crons;

use JTL\Cron\Job\Job;
use JTL\Cron\JobHydrator;
use JTL\Cron\QueueEntry;
use Plugin\jtl_customer_returns\Services\SyncService;
use Plugin\jtl_customer_returns\Repositories\RMARepository;

/**
 * RMA Sync Cron Job - Wawi-Synchronisation
 */
class RMASyncCronJob extends Job
{
    private SyncService $syncService;
    private RMARepository $rmaRepo;
    
    public function __construct(JobHydrator $hydrator, QueueEntry $queueEntry)
    {
        parent::__construct($hydrator, $queueEntry);
        
        $this->syncService = new SyncService();
        $this->rmaRepo = new RMARepository();
    }
    
    /**
     * Cron-Job ausführen
     *
     * @return void
     */
    public function run(): void
    {
        $this->logger->info('RMA Sync Cron Job gestartet');
        
        try {
            // Wawi-Sync aktiviert prüfen
            $autoSync = $this->getConfig('jtl_customer_returns_wawi_auto_sync', true);
            
            if (!$autoSync) {
                $this->logger->info('Wawi Auto-Sync deaktiviert');
                return;
            }
            
            // Sync-Intervall prüfen
            $interval = (int)$this->getConfig('jtl_customer_returns_wawi_sync_interval', 15);
            $lastSync = $this->getLastSyncTime();
            
            if ($lastSync && (time() - $lastSync) < ($interval * 60)) {
                $this->logger->debug('Sync-Intervall noch nicht erreicht');
                return;
            }
            
            // Unsynchronisierte RMAs laden
            $limit = 100; // Max. 100 pro Durchlauf
            $result = $this->syncService->syncPendingRMAs($limit);
            
            $this->logger->info(sprintf(
                'RMA Sync abgeschlossen: %d erfolgreich, %d fehlgeschlagen',
                $result['success'],
                $result['failed']
            ));
            
            // Admin-Benachrichtigung bei Fehlern
            if ($result['failed'] > 0) {
                $this->notifyAdminOnFailure($result['failed']);
            }
            
            // Last-Sync-Time speichern
            $this->saveLastSyncTime();
            
        } catch (\Exception $e) {
            $this->logger->error('RMA Sync Cron Job fehlgeschlagen: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Config-Wert laden
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getConfig(string $key, $default = null)
    {
        return \JTL\Shop::Container()->getConfigService()->get($key, $default);
    }
    
    /**
     * Letzter Sync-Zeitpunkt
     *
     * @return int|null Timestamp
     */
    private function getLastSyncTime(): ?int
    {
        $db = \JTL\Shop::Container()->getDB();
        
        $result = $db->query(
            "SELECT cWert FROM tplugineinstellungen 
             WHERE cName = 'rma_last_sync_time' 
             AND kPlugin = (SELECT kPlugin FROM tplugin WHERE cVerzeichnis = 'jtl_customer_returns') 
             LIMIT 1",
            1
        );
        
        return $result ? (int)$result->cWert : null;
    }
    
    /**
     * Sync-Zeitpunkt speichern
     *
     * @return void
     */
    private function saveLastSyncTime(): void
    {
        $db = \JTL\Shop::Container()->getDB();
        
        $db->queryPrepared(
            "INSERT INTO tplugineinstellungen (kPlugin, cName, cWert) 
             VALUES (
                (SELECT kPlugin FROM tplugin WHERE cVerzeichnis = 'jtl_customer_returns'),
                'rma_last_sync_time',
                :time
             )
             ON DUPLICATE KEY UPDATE cWert = :time",
            ['time' => time()]
        );
    }
    
    /**
     * Admin bei Sync-Fehlern benachrichtigen
     *
     * @param int $failedCount
     * @return void
     */
    private function notifyAdminOnFailure(int $failedCount): void
    {
        $notifyAdmin = (bool)$this->getConfig('jtl_customer_returns_notify_admin_on_sync_error', true);
        
        if (!$notifyAdmin) {
            return;
        }
        
        $adminEmail = $this->getConfig('jtl_customer_returns_admin_notification_email', '');
        
        if (empty($adminEmail)) {
            return;
        }
        
        try {
            $mail = new \JTL\Mail\Mail\Mail();
            $mail->setToMail($adminEmail);
            $mail->setSubject('RMA Wawi-Sync Fehler');
            $mail->setBodyHTML(sprintf(
                '<p>Bei der automatischen RMA-Synchronisation sind %d Fehler aufgetreten.</p>
                 <p>Bitte prüfen Sie die Logs im Admin-Backend.</p>
                 <p>Zeit: %s</p>',
                $failedCount,
                date('d.m.Y H:i:s')
            ));
            
            $mailer = \JTL\Shop::Container()->get(\JTL\Mail\Mailer::class);
            $mailer->send($mail);
            
        } catch (\Exception $e) {
            $this->logger->error('Admin-Benachrichtigung fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
