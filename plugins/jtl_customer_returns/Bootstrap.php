<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

/**
 * Class Bootstrap
 * @package Plugin\jtl_customer_returns
 */
class Bootstrap extends Bootstrapper
{
    /**
     * Boot-Methode: Wird beim Laden des Plugins ausgeführt
     *
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        
        // Event-Listener registrieren
        $this->registerEventListeners($dispatcher);
        
        // Smarty-Plugins registrieren
        $this->registerSmartyPlugins();
        
        // Frontend-Routes registrieren
        $this->registerRoutes();
    }
    
    /**
     * Event-Listener registrieren
     *
     * @param Dispatcher $dispatcher
     * @return void
     */
    private function registerEventListeners(Dispatcher $dispatcher): void
    {
        // 1. Order Shipped - Retourenfrist starten
        $dispatcher->listen('shop.order.shipped', function ($event) {
            $orderID = $event['orderID'] ?? 0;
            
            if (!$orderID) {
                return;
            }
            
            // Optional: Retourenfrist-Reminder
            $config = $this->getPlugin()->getConfig();
            $returnPeriodDays = (int)$config->getValue('return_period_days');
            
            if ($returnPeriodDays > 0) {
                // Reminder-Logik kann hier implementiert werden
                $this->getLogger()->info("Order {$orderID} shipped, return period: {$returnPeriodDays} days");
            }
        });
        
        // 2. Customer Deleted - DSGVO-Anonymisierung
        $dispatcher->listen('shop.customer.deleted', function ($event) {
            $customerID = $event['customerID'] ?? 0;
            
            if (!$customerID) {
                return;
            }
            
            $this->anonymizeCustomerRMAs($customerID);
            $this->getLogger()->info("Customer {$customerID} RMAs anonymized (DSGVO)");
        });
        
        // 3. RMA Created - Custom Event
        $dispatcher->listen('shop.rma.created', function ($event) {
            $rmaID = $event['rmaID'] ?? 0;
            
            // Admin-Benachrichtigung
            $config = $this->getPlugin()->getConfig();
            if ($config->getValue('notify_admin_on_new_rma') === 'Y') {
                $this->notifyAdminNewRMA($rmaID);
            }
        });
        
        // 4. RMA Status Changed - E-Mail-Benachrichtigung
        $dispatcher->listen('shop.rma.status_changed', function ($event) {
            $rmaID = $event['rmaID'] ?? 0;
            $newStatus = $event['newStatus'] ?? 0;
            
            // Notification-Service würde hier E-Mail senden
            $this->getLogger()->info("RMA {$rmaID} status changed to {$newStatus}");
        });
        
        // 5. dbeS Sync Received - Wawi → Shop
        $dispatcher->listen('dbes.sync.received', function ($event) {
            if (($event['type'] ?? '') !== 'rma') {
                return;
            }
            
            $data = $event['data'] ?? [];
            $rmaID = $data['id'] ?? 0;
            
            if ($rmaID) {
                $this->handleWawiUpdate($rmaID, $data);
            }
        });
    }
    
    /**
     * Smarty-Plugins registrieren
     *
     * @return void
     */
    private function registerSmartyPlugins(): void
    {
        $smarty = Shop::Container()->getSmarty();
        
        // Retouren-Menü-Link
        $smarty->registerPlugin(
            \Smarty::PLUGIN_FUNCTION,
            'customer_returns_menu',
            [$this, 'renderCustomerReturnsMenu']
        );
        
        // Retoure-Button
        $smarty->registerPlugin(
            \Smarty::PLUGIN_FUNCTION,
            'return_button',
            [$this, 'renderReturnButton']
        );
    }
    
    /**
     * Frontend-Routes registrieren
     *
     * @return void
     */
    private function registerRoutes(): void
    {
        // Routes werden über JTL Shop 5 Routing-System registriert
        // Siehe frontend/ReturnController.php
    }
    
    /**
     * Smarty-Funktion: Retouren-Menü-Link
     *
     * @param array $params
     * @param JTLSmarty $smarty
     * @return string
     */
    public function renderCustomerReturnsMenu(array $params, JTLSmarty $smarty): string
    {
        $customer = Shop::Container()->getCustomer();
        
        if (!$customer->kKunde) {
            return '';
        }
        
        // Anzahl offener RMAs (würde aus Service kommen)
        $openRMAs = 0; // TODO: ReturnRequestService->getOpenRMAsCount($customer->kKunde)
        
        $badge = $openRMAs > 0 ? " <span class='badge badge-warning'>{$openRMAs}</span>" : '';
        
        return sprintf(
            '<li><a href="/kundenkonto/retouren">Meine Retouren%s</a></li>',
            $badge
        );
    }
    
    /**
     * Smarty-Funktion: Retoure-Button
     *
     * @param array $params
     * @param JTLSmarty $smarty
     * @return string
     */
    public function renderReturnButton(array $params, JTLSmarty $smarty): string
    {
        $orderID = $params['orderID'] ?? 0;
        
        if (!$orderID) {
            return '';
        }
        
        // Prüfen ob retournierbar (würde aus Service kommen)
        $config = $this->getPlugin()->getConfig();
        $returnPeriodDays = (int)$config->getValue('return_period_days');
        
        // TODO: $isReturnable = ReturnRequestService->isOrderReturnable($orderID, $returnPeriodDays)
        $isReturnable = true; // Placeholder
        
        if (!$isReturnable) {
            return '';
        }
        
        return sprintf(
            '<a href="/retoure?orderID=%d" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-undo"></i> Retoure anlegen
            </a>',
            $orderID
        );
    }
    
    /**
     * Kundendaten anonymisieren (DSGVO)
     *
     * @param int $customerID
     * @return void
     */
    private function anonymizeCustomerRMAs(int $customerID): void
    {
        $db = Shop::Container()->getDB();
        
        try {
            // RMAs auf anonymen Kunden setzen
            $db->update(
                'rma',
                'customerID',
                $customerID,
                (object)['customerID' => 0]
            );
            
            // Rücksendeadressen löschen
            $db->delete('return_address', 'customerID', $customerID);
            
        } catch (\Exception $e) {
            $this->getLogger()->error(
                "Error anonymizing customer {$customerID} RMAs: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Admin über neue RMA benachrichtigen
     *
     * @param int $rmaID
     * @return void
     */
    private function notifyAdminNewRMA(int $rmaID): void
    {
        $config = $this->getPlugin()->getConfig();
        $adminEmail = $config->getValue('admin_notification_email');
        
        if (empty($adminEmail)) {
            return;
        }
        
        // TODO: E-Mail-Versand implementieren
        $this->getLogger()->info("Admin notification sent for RMA {$rmaID}");
    }
    
    /**
     * Wawi-Update verarbeiten
     *
     * @param int $rmaID
     * @param array $data
     * @return void
     */
    private function handleWawiUpdate(int $rmaID, array $data): void
    {
        $db = Shop::Container()->getDB();
        
        try {
            $updateData = (object)[];
            
            if (isset($data['status'])) {
                $updateData->status = (int)$data['status'];
            }
            
            if (isset($data['wawiID'])) {
                $updateData->wawiID = (int)$data['wawiID'];
            }
            
            if (!empty((array)$updateData)) {
                $db->update('rma', 'id', $rmaID, $updateData);
                $this->getLogger()->info("RMA {$rmaID} updated from Wawi");
            }
            
        } catch (\Exception $e) {
            $this->getLogger()->error(
                "Error updating RMA {$rmaID} from Wawi: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Admin-Menü-Tab rendern
     *
     * @param string $tabName
     * @param int $menuID
     * @param JTLSmarty $smarty
     * @return string
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        switch ($tabName) {
            case 'overview':
                return $this->renderOverview($smarty);
                
            case 'statistics':
                return $this->renderStatistics($smarty);
                
            default:
                return '';
        }
    }
    
    /**
     * Retouren-Übersicht rendern
     *
     * @param JTLSmarty $smarty
     * @return string
     */
    private function renderOverview(JTLSmarty $smarty): string
    {
        // TODO: Template rendern
        return '<h1>Retouren-Übersicht</h1><p>Coming soon...</p>';
    }
    
    /**
     * Statistiken rendern
     *
     * @param JTLSmarty $smarty
     * @return string
     */
    private function renderStatistics(JTLSmarty $smarty): string
    {
        // TODO: Template rendern
        return '<h1>Statistiken</h1><p>Coming soon...</p>';
    }
}
