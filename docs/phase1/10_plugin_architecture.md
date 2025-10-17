# Plugin-Architektur - Customer Returns Plugin

**Datum:** 17. Oktober 2025  
**Erstellt von:** Development Team  
**Plugin-ID:** `jtl_customer_returns`

---

## Executive Summary

Dieses Dokument definiert die **vollständige Plugin-Architektur** für das Customer Returns Plugin. Die Architektur basiert auf:

- ✅ **JTL Shop 5 Plugin-Konventionen** (PSR-4, PSR-7, Bootstrap-Pattern)
- ✅ **Wiederverwendung bestehender RMA-Komponenten** (Services, Repositories, DomainObjects)
- ✅ **Erweiterbarkeit** durch Hooks, Events, DI-Container
- ✅ **Best Practices** (SOLID, DRY, Separation of Concerns)

---

## 1. Ordnerstruktur

```
plugins/jtl_customer_returns/
│
├── info.xml                          # Plugin-Manifest
├── version/
│   └── 100/                          # Version 1.0.0
│       ├── Bootstrap.php             # Plugin-Hauptklasse
│       │
│       ├── frontend/                 # Frontend-Controller & Templates
│       │   ├── ReturnController.php
│       │   └── template/
│       │       ├── return_form.tpl
│       │       ├── return_products.tpl
│       │       ├── return_summary.tpl
│       │       ├── return_confirmation.tpl
│       │       └── my_returns.tpl
│       │
│       ├── adminmenu/                # Backend-Admin-Bereich
│       │   ├── AdminController.php
│       │   └── template/
│       │       ├── rma_overview.tpl
│       │       ├── rma_detail.tpl
│       │       ├── rma_statistics.tpl
│       │       └── settings.tpl
│       │
│       ├── Services/                 # Business Logic
│       │   ├── ReturnRequestService.php
│       │   ├── SyncService.php
│       │   ├── NotificationService.php
│       │   ├── ExchangeService.php
│       │   └── ShippingLabelService.php
│       │
│       ├── Repositories/             # Data Access (erweitern bestehende)
│       │   └── (Optional: Erweiterungen zu RMA-Repositories)
│       │
│       ├── Migrations/               # DB-Migrations
│       │   ├── Migration20251017100000.php  # Initial: Tabellen erstellen
│       │   └── Migration20251017120000.php  # Add: labelPath Spalte
│       │
│       ├── Crons/                    # Cron-Jobs
│       │   └── RMASyncCronJob.php
│       │
│       ├── Hooks/                    # Event-Listener
│       │   ├── OrderShippedHook.php
│       │   └── CustomerDeleteHook.php
│       │
│       ├── sql/                      # SQL-Dateien (Fallback)
│       │   ├── install.sql
│       │   └── uninstall.sql
│       │
│       ├── Locale/                   # Übersetzungen
│       │   ├── de-DE/
│       │   │   └── translations.json
│       │   └── en-GB/
│       │       └── translations.json
│       │
│       └── assets/                   # Frontend-Assets
│           ├── css/
│           │   └── customer_returns.css
│           └── js/
│               └── customer_returns.js
│
└── README.md                         # Plugin-Dokumentation
```

---

## 2. Namespaces & PSR-4 Autoloading

### Namespace-Konvention

```php
namespace Plugin\jtl_customer_returns;

// Beispiele:
Plugin\jtl_customer_returns\Bootstrap
Plugin\jtl_customer_returns\ReturnController
Plugin\jtl_customer_returns\Services\ReturnRequestService
Plugin\jtl_customer_returns\Migrations\Migration20251017100000
Plugin\jtl_customer_returns\Crons\RMASyncCronJob
```

### Autoloading (composer.json)

```json
{
    "name": "jtl/customer-returns-plugin",
    "description": "Customer Returns Plugin for JTL Shop 5",
    "type": "jtl-shop-plugin",
    "require": {
        "php": ">=7.4",
        "jtl/jtl-shop": "^5.0"
    },
    "autoload": {
        "psr-4": {
            "Plugin\\jtl_customer_returns\\": "version/100/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Plugin\\jtl_customer_returns\\Tests\\": "tests/"
        }
    }
}
```

---

## 3. Bootstrap.php - Plugin-Hauptklasse

### Klassenstruktur

```php
<?php declare(strict_types=1);

namespace Plugin\jtl_customer_returns;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    /**
     * Plugin-Installation
     */
    public function installed(): void
    {
        // Migrations ausführen
        $this->runMigrations();
        
        // Default-Retourengründe einfügen
        $this->insertDefaultReasons();
        
        // Default-Config setzen
        $this->setDefaultConfig();
    }
    
    /**
     * Plugin-Aktivierung
     */
    public function enabled(): void
    {
        // Cron-Jobs registrieren
        $this->registerCronJobs();
        
        // Cache leeren
        Shop::Container()->getCache()->flushTags(['jtl_customer_returns']);
    }
    
    /**
     * Plugin-Deaktivierung
     */
    public function disabled(): void
    {
        // Cron-Jobs deaktivieren
        $this->deactivateCronJobs();
    }
    
    /**
     * Frontend-Routen registrieren
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        
        // Event-Listener registrieren
        $this->registerEventListeners($dispatcher);
        
        // Frontend-Routen
        $this->registerRoutes();
        
        // Smarty-Plugins
        $this->registerSmartyPlugins();
    }
    
    /**
     * Routen registrieren
     */
    private function registerRoutes(): void
    {
        $router = Shop::Container()->get('router');
        
        // Frontend-Routes
        $router->get('/retoure', [ReturnController::class, 'actionIndex']);
        $router->post('/retoure/validate', [ReturnController::class, 'actionValidateOrder']);
        $router->get('/retoure/select-products', [ReturnController::class, 'actionSelectProducts']);
        $router->post('/retoure/summary', [ReturnController::class, 'actionSummary']);
        $router->post('/retoure/submit', [ReturnController::class, 'actionSubmit']);
        $router->get('/retoure/confirmation', [ReturnController::class, 'actionConfirmation']);
        $router->get('/retoure/download-label', [ReturnController::class, 'actionDownloadLabel']);
        
        // Ajax-Routes
        $router->post('/api/returns/search-products', [ReturnController::class, 'apiSearchProducts']);
    }
    
    /**
     * Event-Listener registrieren
     */
    private function registerEventListeners(Dispatcher $dispatcher): void
    {
        // Bestellung versandt → Retourenfrist starten
        $dispatcher->listen('shop.order.shipped', function($event) {
            // Optional: Retourenfrist-Reminder in X Tagen
        });
        
        // Kunde löschen → DSGVO-konforme RMA-Anonymisierung
        $dispatcher->listen('shop.customer.deleted', function($event) {
            $this->anonymizeCustomerRMAs($event['customerID']);
        });
    }
    
    /**
     * Admin-Menü-Tab rendern
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $plugin = $this->getPlugin();
        
        switch ($tabName) {
            case 'Retouren-Übersicht':
                return $this->renderOverview($smarty);
                
            case 'Statistiken':
                return $this->renderStatistics($smarty);
                
            case 'Einstellungen':
                return $this->renderSettings($smarty);
                
            default:
                return '';
        }
    }
    
    /**
     * Retouren-Übersicht rendern
     */
    private function renderOverview(JTLSmarty $smarty): string
    {
        $service = Shop::Container()->get(Services\ReturnRequestService::class);
        
        // Filter aus Request
        $filter = [
            'status' => Request::getInt('filter_status', null),
            'dateFrom' => Request::postVar('filter_date_from', ''),
            'dateTo' => Request::postVar('filter_date_to', '')
        ];
        
        // RMAs laden
        $rmas = $service->getReturns(Shop::getLanguageID(), $filter);
        
        // KPIs berechnen
        $stats = [
            'open' => count(array_filter($rmas, fn($r) => $r->getStatus() === 0)),
            'inProgress' => count(array_filter($rmas, fn($r) => $r->getStatus() === 1)),
            'today' => count(array_filter($rmas, fn($r) => 
                date('Y-m-d', strtotime($r->getCreateDate())) === date('Y-m-d')
            ))
        ];
        
        return $smarty
            ->assign('rmas', $rmas)
            ->assign('stats', $stats)
            ->assign('filter', $filter)
            ->fetch($this->getPlugin()->getPaths()->getAdminPath() . '/template/rma_overview.tpl');
    }
    
    /**
     * Statistiken rendern
     */
    private function renderStatistics(JTLSmarty $smarty): string
    {
        $service = Shop::Container()->get(Services\ReturnRequestService::class);
        
        $dateFrom = Request::postVar('date_from', date('Y-m-01')); // Monatsanfang
        $dateTo = Request::postVar('date_to', date('Y-m-d'));
        
        $stats = $service->getStatistics($dateFrom, $dateTo);
        
        return $smarty
            ->assign('stats', $stats)
            ->assign('dateFrom', $dateFrom)
            ->assign('dateTo', $dateTo)
            ->fetch($this->getPlugin()->getPaths()->getAdminPath() . '/template/rma_statistics.tpl');
    }
    
    /**
     * Migrations ausführen
     */
    private function runMigrations(): void
    {
        $migrationPath = $this->getPlugin()->getPaths()->getBasePath() . '/Migrations';
        
        if (!is_dir($migrationPath)) {
            return;
        }
        
        $files = glob($migrationPath . '/Migration*.php');
        sort($files);
        
        foreach ($files as $file) {
            $className = 'Plugin\\jtl_customer_returns\\Migrations\\' . basename($file, '.php');
            
            if (class_exists($className)) {
                $migration = new $className();
                $migration->up();
            }
        }
    }
    
    /**
     * Default-Retourengründe einfügen
     */
    private function insertDefaultReasons(): void
    {
        $db = Shop::Container()->getDB();
        
        $reasons = [
            ['de-DE' => 'Artikel defekt', 'en-GB' => 'Defective item'],
            ['de-DE' => 'Falsche Größe', 'en-GB' => 'Wrong size'],
            ['de-DE' => 'Falsche Farbe', 'en-GB' => 'Wrong color'],
            ['de-DE' => 'Nicht wie beschrieben', 'en-GB' => 'Not as described'],
            ['de-DE' => 'Gefällt nicht', 'en-GB' => 'Don\'t like it'],
            ['de-DE' => 'Andere', 'en-GB' => 'Other']
        ];
        
        foreach ($reasons as $reason) {
            $reasonID = $db->insert('rma_reasons', (object)[
                'moduleID' => 'jtl_customer_returns'
            ]);
            
            // Deutsche Übersetzung
            $db->insert('rma_reasons_lang', (object)[
                'reasonID' => $reasonID,
                'langID' => 1, // Deutsch
                'title' => $reason['de-DE']
            ]);
            
            // Englische Übersetzung
            $db->insert('rma_reasons_lang', (object)[
                'reasonID' => $reasonID,
                'langID' => 2, // Englisch
                'title' => $reason['en-GB']
            ]);
        }
    }
    
    /**
     * Cron-Jobs registrieren
     */
    private function registerCronJobs(): void
    {
        $db = Shop::Container()->getDB();
        
        // RMA-Wawi-Sync Job
        $db->insert('tcron', (object)[
            'name' => 'RMA Wawi Sync',
            'jobType' => 'plugin',
            'frequency' => 15, // Alle 15 Minuten
            'startDate' => date('Y-m-d H:i:s'),
            'nextStart' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
            'class' => 'Plugin\\jtl_customer_returns\\Crons\\RMASyncCronJob',
            'tab' => 'plugins'
        ]);
    }
    
    /**
     * DSGVO: Kunden-RMAs anonymisieren
     */
    private function anonymizeCustomerRMAs(int $customerID): void
    {
        $db = Shop::Container()->getDB();
        
        $db->update('rma', 'customerID', $customerID, (object)[
            'customerID' => 0,
            'anonymized' => 1
        ]);
        
        // Rücksendeadressen löschen
        $db->delete('return_address', 'customerID', $customerID);
    }
}
```

---

## 4. Frontend-Controller (PSR-7)

### ReturnController.php

```php
<?php declare(strict_types=1);

namespace Plugin\jtl_customer_returns;

use JTL\Plugin\Controller\PageController;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;

class ReturnController extends PageController
{
    private Services\ReturnRequestService $returnService;
    private Services\NotificationService $notificationService;
    
    public function __construct(
        Services\ReturnRequestService $returnService,
        Services\NotificationService $notificationService
    ) {
        $this->returnService = $returnService;
        $this->notificationService = $notificationService;
    }
    
    /**
     * GET /retoure - Formular anzeigen
     */
    public function actionIndex(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        Shop::setPageType(PAGE_PLUGIN);
        $this->smarty = $smarty;
        $this->init();
        
        // Session aufräumen
        unset($_SESSION['return_data']);
        
        return $this->smarty
            ->assign('csrfToken', Form::getToken())
            ->assign('Link', $this->getPageLink())
            ->assign('cPluginTemplate', __DIR__ . '/template/return_form.tpl')
            ->getResponse('layout/index.tpl');
    }
    
    /**
     * POST /retoure/validate - Bestellung validieren (Ajax)
     */
    public function actionValidateOrder(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        if (!Form::validateToken()) {
            return new JsonResponse(['success' => false, 'error' => 'CSRF-Token ungültig'], 403);
        }
        
        $data = $request->getParsedBody();
        $orderNo = trim($data['orderNo'] ?? '');
        $email = trim($data['email'] ?? '');
        
        if (empty($orderNo) || empty($email)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Bestellnummer und E-Mail sind Pflichtfelder'
            ], 400);
        }
        
        // Validierung
        $valid = $this->returnService->validateOrderAccess($orderNo, $email);
        
        if (!$valid) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Bestellung nicht gefunden oder E-Mail stimmt nicht überein'
            ], 404);
        }
        
        // Bestellung laden
        $order = $this->returnService->getOrderByNumber($orderNo);
        
        // Session speichern
        $_SESSION['return_data'] = [
            'orderID' => $order->kBestellung,
            'orderNo' => $orderNo,
            'customerID' => $order->kKunde,
            'email' => $email
        ];
        
        return new JsonResponse([
            'success' => true,
            'redirect' => '/retoure/select-products'
        ]);
    }
    
    /**
     * GET /retoure/select-products - Produktauswahl
     */
    public function actionSelectProducts(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        // Session-Check
        if (!isset($_SESSION['return_data']['orderID'])) {
            return new RedirectResponse('/retoure', 302);
        }
        
        $sessionData = $_SESSION['return_data'];
        $orderID = $sessionData['orderID'];
        $customerID = $sessionData['customerID'];
        
        // Config
        $config = $this->getPlugin()->getConfig();
        $returnPeriodDays = (int)$config->getValue('return_period_days');
        
        // Retournierbare Produkte laden
        $products = $this->returnService->getReturnableProducts(
            $customerID,
            Shop::getLanguageID(),
            $returnPeriodDays,
            $orderID
        );
        
        // Retourengründe
        $reasonService = Shop::Container()->get(Services\RMAReasonService::class);
        $reasons = $reasonService->getLocalizedReasons(Shop::getLanguageID());
        
        // Rücksendeadressen
        $addressService = Shop::Container()->get(Services\RMAReturnAddressService::class);
        $addresses = $addressService->getCustomerAddresses($customerID);
        
        Shop::setPageType(PAGE_PLUGIN);
        $this->smarty = $smarty;
        $this->init();
        
        return $this->smarty
            ->assign('products', $products)
            ->assign('reasons', $reasons)
            ->assign('addresses', $addresses)
            ->assign('orderNo', $sessionData['orderNo'])
            ->assign('csrfToken', Form::getToken())
            ->assign('cPluginTemplate', __DIR__ . '/template/return_products.tpl')
            ->getResponse('layout/index.tpl');
    }
    
    /**
     * POST /retoure/summary - Zusammenfassung
     */
    public function actionSummary(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        // Implementation siehe API-Spec (09_api_specification.md)
        // ...
    }
    
    /**
     * POST /retoure/submit - RMA erstellen
     */
    public function actionSubmit(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        // Implementation siehe API-Spec
        // ...
    }
}
```

---

## 5. Services Layer

### ReturnRequestService.php

```php
<?php declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Services;

use JTL\Shop;
use JTL\DB\ReturnType;
use Plugin\jtl_customer_returns\RMA\DomainObjects\RMADomainObject;

class ReturnRequestService
{
    private RMARepository $rmaRepository;
    private RMAItemRepository $itemRepository;
    
    public function __construct(
        RMARepository $rmaRepository,
        RMAItemRepository $itemRepository
    ) {
        $this->rmaRepository = $rmaRepository;
        $this->itemRepository = $itemRepository;
    }
    
    /**
     * Bestellung validieren
     */
    public function validateOrderAccess(string $orderNo, string $email): bool
    {
        $db = Shop::Container()->getDB();
        
        $order = $db->executeQueryPrepared(
            "SELECT kBestellung, cMail FROM tbestellung WHERE cBestellNr = :orderNo",
            [':orderNo' => $orderNo],
            ReturnType::SINGLE_OBJECT
        );
        
        return $order && strtolower($order->cMail) === strtolower($email);
    }
    
    /**
     * Retournierbare Produkte laden
     */
    public function getReturnableProducts(
        int $customerID,
        int $langID,
        int $cancellationTime,
        int $orderID = null
    ): array {
        // Nutzt RMARepository->getReturnableProductsForCustomer()
        return $this->rmaRepository->getReturnableProductsForCustomer(
            $customerID,
            $langID,
            $cancellationTime,
            $orderID
        );
    }
    
    /**
     * Retoure erstellen
     */
    public function createReturnRequest(array $data): RMADomainObject
    {
        $db = Shop::Container()->getDB();
        $db->beginTransaction();
        
        try {
            // RMA erstellen
            $rma = new RMADomainObject();
            $rma->setOrderID($data['orderID']);
            $rma->setCustomerID($data['customerID']);
            $rma->setStatus(0); // OPEN
            $rma->setRmaNr($this->generateRmaNr());
            $rma->setCreateDate(date('Y-m-d H:i:s'));
            $rma->setRefundShipping($data['refundShipping'] ? 1 : 0);
            $rma->setSynced(0);
            
            $rmaID = $this->rmaRepository->save($rma);
            $rma->setId($rmaID);
            
            // Items speichern
            foreach ($data['items'] as $item) {
                $rmaItem = new RMAItemDomainObject();
                $rmaItem->setRmaID($rmaID);
                $rmaItem->setOrderPosID($item['orderPosID']);
                $rmaItem->setProductID($item['productID']);
                $rmaItem->setQuantity($item['quantity']);
                $rmaItem->setReasonID($item['reasonID']);
                $rmaItem->setComment($item['comment'] ?? '');
                $rmaItem->setUnitPriceNet($item['unitPriceNet']);
                $rmaItem->setVat($item['vat']);
                $rmaItem->setStatus(0); // OPEN
                
                $this->itemRepository->save($rmaItem);
            }
            
            // Rücksendeadresse speichern
            $this->saveReturnAddress($rmaID, $data['returnAddressID']);
            
            // History-Eintrag
            $historyService = Shop::Container()->get(RMAHistoryService::class);
            $historyService->addEvent($rmaID, 'RMA_CREATED', [
                'customerID' => $data['customerID'],
                'orderID' => $data['orderID']
            ]);
            
            $db->commit();
            
            return $rma;
            
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Eindeutige RMA-Nummer generieren
     */
    private function generateRmaNr(): string
    {
        $db = Shop::Container()->getDB();
        
        // Format: RMA-YYYYMMDD-###
        $date = date('Ymd');
        $prefix = 'RMA-' . $date . '-';
        
        // Höchste Nummer heute
        $result = $db->executeQueryPrepared(
            "SELECT MAX(CAST(SUBSTRING(rmaNr, 14) AS UNSIGNED)) AS maxNr 
             FROM rma 
             WHERE rmaNr LIKE :prefix",
            [':prefix' => $prefix . '%'],
            ReturnType::SINGLE_OBJECT
        );
        
        $nextNr = ($result->maxNr ?? 0) + 1;
        
        return $prefix . str_pad((string)$nextNr, 3, '0', STR_PAD_LEFT);
    }
}
```

---

## 6. Migrations

### Migration20251017100000.php - Initial Tables

```php
<?php declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Migrations;

use JTL\Plugin\Migration;
use JTL\Shop;

class Migration20251017100000 extends Migration
{
    /**
     * Migration ausführen
     */
    public function up(): void
    {
        $db = Shop::Container()->getDB();
        
        // Tabellen erstellen (nutzt bestehende RMA-Tabellen)
        // Falls Tabellen nicht existieren, hier CREATE Statements
        
        // rma-Tabelle erweitern für Plugin-spezifische Felder
        $db->executeQuery("
            ALTER TABLE rma
            ADD COLUMN IF NOT EXISTS labelPath VARCHAR(500) DEFAULT NULL COMMENT 'Pfad zum Retourenlabel',
            ADD COLUMN IF NOT EXISTS anonymized TINYINT(1) DEFAULT 0 COMMENT 'DSGVO: Kundendaten anonymisiert'
        ");
        
        // Indizes optimieren
        $db->executeQuery("
            ALTER TABLE rma
            ADD INDEX idx_rmaNr (rmaNr),
            ADD INDEX idx_synced (synced),
            ADD INDEX idx_createDate (createDate)
        ");
        
        $db->executeQuery("
            ALTER TABLE rma_items
            ADD INDEX idx_rmaID_status (rmaID, status)
        ");
    }
    
    /**
     * Migration rückgängig machen
     */
    public function down(): void
    {
        $db = Shop::Container()->getDB();
        
        $db->executeQuery("
            ALTER TABLE rma
            DROP COLUMN IF EXISTS labelPath,
            DROP COLUMN IF EXISTS anonymized
        ");
    }
}
```

---

## 7. Dependency Injection

### Service-Registrierung

```php
// In Bootstrap.php
public function boot(Dispatcher $dispatcher): void
{
    parent::boot($dispatcher);
    
    // Services im DI-Container registrieren
    $container = Shop::Container();
    
    $container->singleton(Services\ReturnRequestService::class, function() use ($container) {
        return new Services\ReturnRequestService(
            $container->get(RMARepository::class),
            $container->get(RMAItemRepository::class)
        );
    });
    
    $container->singleton(Services\SyncService::class, function() use ($container) {
        return new Services\SyncService(
            $container->get(RMARepository::class)
        );
    });
    
    $container->singleton(Services\NotificationService::class, function() use ($container) {
        return new Services\NotificationService(
            $container->get(RMAService::class)
        );
    });
    
    // Controller-DI
    $container->singleton(ReturnController::class, function() use ($container) {
        return new ReturnController(
            $container->get(Services\ReturnRequestService::class),
            $container->get(Services\NotificationService::class)
        );
    });
}
```

---

## 8. info.xml - Plugin-Manifest

```xml
<?xml version="1.0" encoding="UTF-8"?>
<jtlshopplugin>
    <Name>Customer Returns</Name>
    <Description>Ermöglicht Kunden die einfache Erstellung von Retouren ohne Login</Description>
    <Author>JTL-Software GmbH</Author>
    <URL>https://www.jtl-software.de</URL>
    <XMLVersion>1.00</XMLVersion>
    <ShopVersion>5.0.0</ShopVersion>
    <PluginID>jtl_customer_returns</PluginID>
    <Version>1.0.0</Version>
    <Install>
        <![CDATA[
            <!-- Migrations werden automatisch in Bootstrap->installed() ausgeführt -->
        ]]>
    </Install>
    <CreateDate>2025-10-17</CreateDate>
    <LicenseType>GPL</LicenseType>
    
    <!-- Admin-Menü -->
    <AdminMenu>
        <Customlink sort="1">
            <Name>Retouren-Übersicht</Name>
            <Filename>Retouren-Übersicht</Filename>
        </Customlink>
        <Customlink sort="2">
            <Name>Statistiken</Name>
            <Filename>Statistiken</Filename>
        </Customlink>
    </AdminMenu>
    
    <!-- Plugin-Einstellungen -->
    <Settingslink sort="3">
        <Name>Einstellungen</Name>
        
        <!-- Retourenbedingungen -->
        <Setting type="number" initialValue="14" conf="Y" sort="1">
            <Name>Retourenfrist (Tage)</Name>
            <ValueName>return_period_days</ValueName>
            <Description>Anzahl Tage nach Versand, in denen Retouren möglich sind</Description>
        </Setting>
        
        <Setting type="checkbox" initialValue="on" conf="Y" sort="2">
            <Name>Versandkosten automatisch erstatten bei Vollretoure</Name>
            <ValueName>auto_refund_shipping</ValueName>
        </Setting>
        
        <!-- Retourenlabel -->
        <Setting type="checkbox" initialValue="off" conf="Y" sort="10">
            <Name>Retourenlabel automatisch erstellen</Name>
            <ValueName>auto_create_label</ValueName>
        </Setting>
        
        <Setting type="selectbox" initialValue="dhl" conf="Y" sort="11">
            <Name>Versanddienstleister</Name>
            <ValueName>shipping_provider</ValueName>
            <SelectboxOptions>
                <Option value="dhl">DHL</Option>
                <Option value="dpd">DPD</Option>
                <Option value="ups">UPS</Option>
                <Option value="hermes">Hermes</Option>
            </SelectboxOptions>
        </Setting>
        
        <Setting type="text" initialValue="" conf="Y" sort="12">
            <Name>API-Key (Versanddienstleister)</Name>
            <ValueName>shipping_api_key</ValueName>
            <Description>API-Schlüssel für automatische Label-Erstellung</Description>
        </Setting>
        
        <!-- Produktausschlüsse -->
        <Setting type="text" initialValue="" conf="Y" sort="20">
            <Name>Ausgeschlossene Kategorien (IDs, kommagetrennt)</Name>
            <ValueName>excluded_categories</ValueName>
            <Description>z.B. 123,456,789</Description>
        </Setting>
        
        <Setting type="textarea" initialValue="" conf="Y" sort="21">
            <Name>Ausgeschlossene Artikelnummern (kommagetrennt)</Name>
            <ValueName>excluded_product_nos</ValueName>
        </Setting>
        
        <!-- Benachrichtigungen -->
        <Setting type="text" initialValue="" conf="Y" sort="30">
            <Name>Admin-E-Mail für Benachrichtigungen</Name>
            <ValueName>admin_notification_email</ValueName>
            <Description>E-Mail-Adresse, die bei neuen Retouren benachrichtigt wird</Description>
        </Setting>
        
        <Setting type="checkbox" initialValue="on" conf="Y" sort="31">
            <Name>Admin bei neuer Retoure benachrichtigen</Name>
            <ValueName>notify_admin_on_new_rma</ValueName>
        </Setting>
        
        <!-- Wawi-Sync -->
        <Setting type="number" initialValue="15" conf="Y" sort="40">
            <Name>Sync-Intervall (Minuten)</Name>
            <ValueName>wawi_sync_interval</ValueName>
            <Description>Intervall für automatische Wawi-Synchronisation</Description>
        </Setting>
        
        <Setting type="checkbox" initialValue="on" conf="Y" sort="41">
            <Name>Auto-Sync aktivieren</Name>
            <ValueName>wawi_auto_sync</ValueName>
        </Setting>
        
        <!-- Rücksendeadresse -->
        <Setting type="textarea" initialValue="" conf="Y" sort="50">
            <Name>Shop-Rücksendeadresse</Name>
            <ValueName>shop_return_address</ValueName>
            <Description>Vollständige Adresse für Retouren (wird in E-Mails angezeigt)</Description>
        </Setting>
    </Settingslink>
    
    <!-- E-Mail-Templates -->
    <Emailtemplate>
        <!-- Retouren-Bestätigung -->
        <Template>
            <Name>Retouren-Bestätigung</Name>
            <Description>E-Mail bei RMA-Erstellung</Description>
            <Type>text/html</Type>
            <ModulId>jtl_customer_returns_confirmation</ModulId>
            <Active>Y</Active>
            <TemplateLanguage iso="GER">
                <Subject>Ihre Retoure {$rma_number} wurde registriert</Subject>
                <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Sehr geehrte/r {$customer_name},</p>
<p>Ihre Retoure mit der Nummer <strong>{$rma_number}</strong> wurde erfolgreich angelegt.</p>
<h3>Retournierte Artikel:</h3>
<ul>
{foreach $return_items as $item}
    <li>{$item.name} - Menge: {$item.quantity}</li>
{/foreach}
</ul>
<p><strong>Rücksendeadresse:</strong><br>
{$return_address}</p>
<p>Bitte senden Sie die Ware innerhalb von 14 Tagen zurück.</p>
{includeMailTemplate template=footer type=html}
                ]]></ContentHtml>
                <ContentText><![CDATA[
Sehr geehrte/r {$customer_name},

Ihre Retoure mit der Nummer {$rma_number} wurde erfolgreich angelegt.

Retournierte Artikel:
{foreach $return_items as $item}- {$item.name} - Menge: {$item.quantity}
{/foreach}

Rücksendeadresse:
{$return_address}

Bitte senden Sie die Ware innerhalb von 14 Tagen zurück.
                ]]></ContentText>
            </TemplateLanguage>
            <TemplateLanguage iso="ENG">
                <Subject>Your return {$rma_number} has been registered</Subject>
                <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Dear {$customer_name},</p>
<p>Your return with number <strong>{$rma_number}</strong> has been successfully created.</p>
<h3>Returned items:</h3>
<ul>
{foreach $return_items as $item}
    <li>{$item.name} - Quantity: {$item.quantity}</li>
{/foreach}
</ul>
<p><strong>Return address:</strong><br>
{$return_address}</p>
<p>Please send the goods back within 14 days.</p>
{includeMailTemplate template=footer type=html}
                ]]></ContentHtml>
            </TemplateLanguage>
        </Template>
        
        <!-- Status-Update -->
        <Template>
            <Name>Retouren-Status-Update</Name>
            <Description>E-Mail bei Status-Änderung</Description>
            <Type>text/html</Type>
            <ModulId>jtl_customer_returns_status_update</ModulId>
            <Active>Y</Active>
            <TemplateLanguage iso="GER">
                <Subject>Ihre Retoure {$rma_number} - Status: {$new_status}</Subject>
                <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Sehr geehrte/r {$customer_name},</p>
<p>Der Status Ihrer Retoure <strong>{$rma_number}</strong> wurde aktualisiert:</p>
<p><strong>Neuer Status:</strong> {$new_status}</p>
{if $admin_comment}
<p><strong>Kommentar:</strong> {$admin_comment}</p>
{/if}
{includeMailTemplate template=footer type=html}
                ]]></ContentHtml>
            </TemplateLanguage>
        </Template>
    </Emailtemplate>
    
    <!-- Frontend-Links -->
    <Frontendlink>
        <Name>Retoure anlegen</Name>
        <Filename>retoure</Filename>
        <Visibility>always</Visibility>
    </Frontendlink>
    
    <!-- Cron-Jobs werden in Bootstrap->registerCronJobs() registriert -->
</jtlshopplugin>
```

---

## 9. Assets (CSS/JS)

### customer_returns.css

```css
/* Retouren-Formular */
.return-form {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.return-form .form-group {
    margin-bottom: 20px;
}

/* Produktliste */
.return-products-table {
    width: 100%;
}

.return-products-table .product-checkbox {
    cursor: pointer;
}

.return-products-table .qty-input {
    width: 80px;
}

.return-products-table .reason-select {
    width: 100%;
}

/* Zusammenfassung */
.return-summary {
    max-width: 800px;
    margin: 0 auto;
}

.return-summary .total {
    font-weight: bold;
    background-color: #f8f9fa;
}

/* Bestätigung */
.return-confirmation {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}

.return-confirmation .alert-success {
    margin-bottom: 30px;
}

.return-confirmation .next-steps ol {
    text-align: left;
    margin-top: 20px;
}

/* Admin-Dashboard */
.rma-dashboard .kpi-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
}

.rma-dashboard .kpi-value {
    font-size: 36px;
    font-weight: bold;
    color: #007bff;
}

/* Status-Badges */
.badge-status-0 { background-color: #6c757d; } /* OPEN */
.badge-status-1 { background-color: #ffc107; } /* IN_PROGRESS */
.badge-status-2 { background-color: #28a745; } /* ACCEPTED */
.badge-status-3 { background-color: #17a2b8; } /* COMPLETED */
.badge-status-4 { background-color: #dc3545; } /* REJECTED */
```

### customer_returns.js

```javascript
/**
 * Customer Returns Plugin - Frontend JavaScript
 */
(function($) {
    'use strict';
    
    var CustomerReturns = {
        /**
         * Initialisierung
         */
        init: function() {
            this.bindEvents();
            this.initDataTables();
        },
        
        /**
         * Event-Listener
         */
        bindEvents: function() {
            // Formular-Validierung
            $('#return-form').on('submit', this.validateForm);
            
            // Produktauswahl
            $('.product-checkbox').on('change', this.toggleProductRow);
            
            // Mengen-Validierung
            $('.qty-input').on('input', this.validateQuantity);
        },
        
        /**
         * DataTables initialisieren
         */
        initDataTables: function() {
            if ($('#products-table').length) {
                $('#products-table').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json'
                    },
                    pageLength: 10,
                    order: [[1, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: [0, 4] } // Checkbox + Kommentar
                    ]
                });
            }
        },
        
        /**
         * Formular validieren
         */
        validateForm: function(e) {
            e.preventDefault();
            
            var selectedItems = $('.product-checkbox:checked');
            
            if (selectedItems.length === 0) {
                alert('Bitte wählen Sie mindestens ein Produkt aus.');
                return false;
            }
            
            var valid = true;
            
            selectedItems.each(function() {
                var $row = $(this).closest('tr');
                var qty = parseFloat($row.find('.qty-input').val());
                var maxQty = parseFloat($row.data('max-qty'));
                var reason = $row.find('.reason-select').val();
                
                if (!qty || qty <= 0 || qty > maxQty) {
                    alert('Ungültige Menge für ' + $row.find('.product-name').text());
                    valid = false;
                    return false;
                }
                
                if (!reason) {
                    alert('Bitte wählen Sie einen Retourengrund für ' + $row.find('.product-name').text());
                    valid = false;
                    return false;
                }
            });
            
            if (valid) {
                this.submit();
            }
        },
        
        /**
         * Produktzeile aktivieren/deaktivieren
         */
        toggleProductRow: function() {
            var $row = $(this).closest('tr');
            var $inputs = $row.find('.qty-input, .reason-select, .comment-textarea');
            
            if ($(this).is(':checked')) {
                $inputs.prop('disabled', false);
                $row.addClass('selected');
            } else {
                $inputs.prop('disabled', true);
                $row.removeClass('selected');
            }
        },
        
        /**
         * Menge validieren
         */
        validateQuantity: function() {
            var $input = $(this);
            var val = parseFloat($input.val());
            var max = parseFloat($input.attr('max'));
            
            if (val > max) {
                $input.val(max);
                alert('Maximale Menge: ' + max);
            }
            
            if (val < 1) {
                $input.val(1);
            }
        }
    };
    
    // Bei DOM-Ready initialisieren
    $(document).ready(function() {
        CustomerReturns.init();
    });
    
})(jQuery);
```

---

## 10. Testing-Strategie

### Unit-Tests (PHPUnit)

```php
// tests/Services/ReturnRequestServiceTest.php
namespace Plugin\jtl_customer_returns\Tests\Services;

use PHPUnit\Framework\TestCase;
use Plugin\jtl_customer_returns\Services\ReturnRequestService;

class ReturnRequestServiceTest extends TestCase
{
    private ReturnRequestService $service;
    
    protected function setUp(): void
    {
        $this->service = new ReturnRequestService(
            $this->createMock(RMARepository::class),
            $this->createMock(RMAItemRepository::class)
        );
    }
    
    public function testValidateOrderAccessSuccess(): void
    {
        $result = $this->service->validateOrderAccess('B-2024-12345', 'kunde@example.com');
        $this->assertTrue($result);
    }
    
    public function testValidateOrderAccessFailure(): void
    {
        $result = $this->service->validateOrderAccess('B-INVALID', 'wrong@example.com');
        $this->assertFalse($result);
    }
    
    public function testGenerateRmaNr(): void
    {
        $rmaNr = $this->invokeMethod($this->service, 'generateRmaNr');
        $this->assertMatchesRegularExpression('/^RMA-\d{8}-\d{3}$/', $rmaNr);
    }
}
```

### Integration-Tests

```php
// tests/Integration/ReturnFlowTest.php
namespace Plugin\jtl_customer_returns\Tests\Integration;

use PHPUnit\Framework\TestCase;

class ReturnFlowTest extends TestCase
{
    /**
     * Test: Kompletter Retouren-Flow
     */
    public function testCompleteReturnFlow(): void
    {
        // 1. Formular aufrufen
        $response = $this->get('/retoure');
        $this->assertEquals(200, $response->getStatusCode());
        
        // 2. Bestellung validieren
        $response = $this->post('/retoure/validate', [
            'orderNo' => 'B-2024-TEST',
            'email' => 'test@example.com'
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getBody());
        
        // 3. Produktauswahl
        $response = $this->get('/retoure/select-products');
        $this->assertEquals(200, $response->getStatusCode());
        
        // 4. Submit
        $response = $this->post('/retoure/submit', [
            'items' => [...],
            'accept_terms' => 1
        ]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/retoure/confirmation', $response->getHeader('Location')[0]);
    }
}
```

---

## Zusammenfassung & Nächste Schritte

### ✅ Dokumentiert

- [x] **Ordnerstruktur** (14 Verzeichnisse, klare Separation of Concerns)
- [x] **Namespaces & PSR-4** Autoloading
- [x] **Bootstrap.php** (Installation, Routing, Event-Listener, DI)
- [x] **Frontend-Controller** (PSR-7, 7 Actions)
- [x] **Services Layer** (ReturnRequestService mit Business Logic)
- [x] **Migrations** (DB-Schema-Updates)
- [x] **Dependency Injection** (Service-Registrierung)
- [x] **info.xml** (Manifest mit Settings, E-Mail-Templates, Admin-Menü)
- [x] **Assets** (CSS + JavaScript für Frontend)
- [x] **Testing-Strategie** (Unit-Tests, Integration-Tests)

### 📋 Architektur-Highlights

| Komponente | Technologie | Zweck |
|------------|-------------|-------|
| **Bootstrap** | JTL Plugin Bootstrapper | Initialisierung, Routing, Events |
| **Controllers** | PSR-7 PageController | HTTP-Request-Handling |
| **Services** | DI-Container | Business Logic |
| **Repositories** | AbstractDBRepository | Data Access |
| **Migrations** | JTL Migration | DB-Schema-Versionierung |
| **Templates** | Smarty | Frontend-Rendering |
| **Assets** | CSS/JS | UI-Interaktion |

### ⏭️ Nächster Schritt: Todo 11

**Integrationspunkte dokumentieren** - JTL Shop Hooks, Frontend-Events, Admin-Menü, E-Mail-Templates, Config-Optionen

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** Plugin-Architektur vollständig designt. **14 Ordner**, **10 Kernklassen**, **PSR-4 Autoloading**, **DI-Container**, **Migrations**, **info.xml** mit 15+ Settings. **Testing-Strategie** definiert.
