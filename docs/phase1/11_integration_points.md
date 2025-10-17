# Integrationspunkte - Customer Returns Plugin

**Datum:** 17. Oktober 2025  
**Erstellt von:** Development Team  
**Plugin-ID:** `jtl_customer_returns`

---

## Executive Summary

Dieses Dokument beschreibt **alle Integrationspunkte** zwischen dem Customer Returns Plugin und JTL Shop 5. Die Integration erfolgt über:

- ✅ **JTL Shop Events** (Dispatcher-Pattern)
- ✅ **Frontend-Hooks** (Template-Integration)
- ✅ **Admin-Menü** (Backend-Tabs)
- ✅ **E-Mail-Templates** (Notification-System)
- ✅ **Config-Optionen** (Plugin-Settings)
- ✅ **Wawi-Synchronisation** (dbeS-Queue)

---

## 1. JTL Shop Events (Event Dispatcher)

### 1.1 shop.order.shipped

**Trigger:** Wenn eine Bestellung versandt wird  
**Use-Case:** Retourenfrist starten, optionaler Reminder

```php
// In Bootstrap->registerEventListeners()
$dispatcher->listen('shop.order.shipped', function($event) {
    $orderID = $event['orderID'] ?? 0;
    
    if (!$orderID) {
        return;
    }
    
    // Optional: Retourenfrist-Reminder in Warteschlange
    $config = $this->getPlugin()->getConfig();
    $returnPeriodDays = (int)$config->getValue('return_period_days');
    
    if ($returnPeriodDays > 0) {
        $reminderDate = date('Y-m-d H:i:s', strtotime("+{$returnPeriodDays} days"));
        
        // Reminder-Job erstellen (z.B. nach 10 von 14 Tagen)
        $reminderDays = max(1, $returnPeriodDays - 4);
        $this->scheduleReturnReminder($orderID, $reminderDays);
    }
});
```

**Event-Daten:**
```php
[
    'orderID' => 123,
    'order' => Bestellung-Objekt,
    'shipmentDate' => '2025-10-17 14:30:00'
]
```

---

### 1.2 shop.customer.deleted

**Trigger:** Wenn ein Kunde gelöscht wird (DSGVO)  
**Use-Case:** RMA-Daten anonymisieren

```php
$dispatcher->listen('shop.customer.deleted', function($event) {
    $customerID = $event['customerID'] ?? 0;
    
    if (!$customerID) {
        return;
    }
    
    // RMAs anonymisieren
    $this->anonymizeCustomerRMAs($customerID);
    
    // Logging
    $this->getLogger()->info("Customer {$customerID} RMAs anonymized (DSGVO)");
});
```

**Anonymisierungs-Logik:**
```php
private function anonymizeCustomerRMAs(int $customerID): void
{
    $db = Shop::Container()->getDB();
    
    // RMAs auf anonymen Kunden setzen
    $db->update('rma', 'customerID', $customerID, (object)[
        'customerID' => 0,
        'anonymized' => 1
    ]);
    
    // Rücksendeadressen löschen
    $db->delete('return_address', 'customerID', $customerID);
    
    // RMA-Historie aktualisieren (Namen entfernen)
    $rmaIDs = $db->selectAll('rma', 'customerID', 0, 'id');
    
    foreach ($rmaIDs as $rma) {
        $historyService = Shop::Container()->get(RMAHistoryService::class);
        $historyService->addEvent($rma->id, 'CUSTOMER_ANONYMIZED', [
            'date' => date('Y-m-d H:i:s')
        ]);
    }
}
```

---

### 1.3 shop.rma.created (Custom Event)

**Trigger:** Plugin-eigenes Event nach RMA-Erstellung  
**Use-Case:** Andere Plugins können auf RMA-Erstellung reagieren

```php
// In ReturnRequestService->createReturnRequest()
$rma = $this->rmaRepository->save($rmaData);

// Event dispatchen
Shop::Container()
    ->get(Dispatcher::class)
    ->fire('shop.rma.created', [
        'rmaID' => $rma->getId(),
        'rma' => $rma,
        'customerID' => $rma->getCustomerID(),
        'orderID' => $rma->getOrderID()
    ]);
```

**Use-Cases für andere Plugins:**
- **Analytics-Plugin:** RMA-Tracking in Google Analytics
- **CRM-Plugin:** Automatisches Ticket erstellen
- **Loyalty-Plugin:** Bonus-Punkte anpassen

---

### 1.4 shop.rma.status_changed (Custom Event)

**Trigger:** Wenn RMA-Status geändert wird  
**Use-Case:** E-Mail-Benachrichtigung, Wawi-Sync

```php
// In RMAService->updateStatus()
public function updateStatus(int $rmaID, int $newStatus, string $comment = ''): void
{
    $rma = $this->getReturn($rmaID);
    $oldStatus = $rma->getStatus();
    
    $rma->setStatus($newStatus);
    $this->repository->save($rma);
    
    // History-Event
    $this->historyService->addEvent($rmaID, 'STATUS_CHANGED', [
        'oldStatus' => $oldStatus,
        'newStatus' => $newStatus,
        'comment' => $comment
    ]);
    
    // Event dispatchen
    Shop::Container()
        ->get(Dispatcher::class)
        ->fire('shop.rma.status_changed', [
            'rmaID' => $rmaID,
            'oldStatus' => $oldStatus,
            'newStatus' => $newStatus,
            'comment' => $comment
        ]);
}
```

**Event-Listener (z.B. für E-Mail):**
```php
$dispatcher->listen('shop.rma.status_changed', function($event) {
    $rmaID = $event['rmaID'];
    $newStatus = $event['newStatus'];
    
    // E-Mail senden
    $notificationService = Shop::Container()->get(NotificationService::class);
    $notificationService->sendStatusUpdate($rmaID, $newStatus);
});
```

---

## 2. Frontend-Hooks (Template-Integration)

### 2.1 Kundenkonto-Menü erweitern

**Ziel:** "Meine Retouren"-Link im Kundenkonto-Menü

**Template:** `tpl_inc/header.tpl` oder `account/menu.tpl`

**Hook-Methode:**
```php
// In Bootstrap.php
public function boot(Dispatcher $dispatcher): void
{
    parent::boot($dispatcher);
    
    // Smarty-Plugin registrieren
    Shop::Container()->getSmarty()->registerPlugin(
        Smarty::PLUGIN_FUNCTION,
        'customer_returns_menu',
        [$this, 'renderCustomerReturnsMenu']
    );
}

/**
 * Smarty-Funktion: Retouren-Menü-Link
 */
public function renderCustomerReturnsMenu(array $params, Smarty $smarty): string
{
    $customer = Shop::Container()->getCustomer();
    
    if (!$customer->kKunde) {
        return '';
    }
    
    // Anzahl offener RMAs
    $service = Shop::Container()->get(Services\ReturnRequestService::class);
    $openRMAs = $service->getOpenRMAsCount($customer->kKunde);
    
    $badge = $openRMAs > 0 ? " <span class='badge badge-warning'>{$openRMAs}</span>" : '';
    
    return sprintf(
        '<li><a href="/kundenkonto/retouren">Meine Retouren%s</a></li>',
        $badge
    );
}
```

**Template-Verwendung:**
```smarty
{* In account/menu.tpl *}
<ul class="account-menu">
    <li><a href="/kundenkonto/bestellungen">Bestellungen</a></li>
    {customer_returns_menu}  {* Plugin-Hook *}
    <li><a href="/kundenkonto/daten">Persönliche Daten</a></li>
</ul>
```

---

### 2.2 Bestelldetails erweitern

**Ziel:** "Retoure anlegen"-Button in Bestellübersicht

**Template:** `account/orders.tpl`

**Smarty-Plugin:**
```php
/**
 * Smarty-Funktion: Retoure-Button für Bestellung
 */
public function renderReturnButton(array $params, Smarty $smarty): string
{
    $orderID = $params['orderID'] ?? 0;
    
    if (!$orderID) {
        return '';
    }
    
    // Prüfen ob retournierbar
    $service = Shop::Container()->get(Services\ReturnRequestService::class);
    $config = $this->getPlugin()->getConfig();
    $returnPeriodDays = (int)$config->getValue('return_period_days');
    
    $isReturnable = $service->isOrderReturnable($orderID, $returnPeriodDays);
    
    if (!$isReturnable) {
        return '';
    }
    
    return '<a href="/retoure?orderID=' . $orderID . '" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-undo"></i> Retoure anlegen
            </a>';
}
```

**Template-Verwendung:**
```smarty
{* In account/orders.tpl *}
{foreach $orders as $order}
    <tr>
        <td>{$order->cBestellNr}</td>
        <td>{$order->dErstellt}</td>
        <td>
            <a href="/kundenkonto/bestellung/{$order->kBestellung}">Details</a>
            {return_button orderID=$order->kBestellung}
        </td>
    </tr>
{/foreach}
```

---

### 2.3 Footer-Link

**Ziel:** "Retoure"-Link im Footer (für Gäste ohne Login)

**Template:** `layout/footer.tpl`

```smarty
<footer>
    <ul class="footer-links">
        <li><a href="/versand">Versand</a></li>
        <li><a href="/retoure">Retoure anlegen</a></li>
        <li><a href="/widerrufsbelehrung">Widerrufsbelehrung</a></li>
    </ul>
</footer>
```

---

## 3. Admin-Menü Integration

### 3.1 Plugin-Menü-Tabs

**Definiert in:** `info.xml`

```xml
<AdminMenu>
    <Customlink sort="1">
        <Name>Retouren-Übersicht</Name>
        <Filename>Retouren-Übersicht</Filename>
        <Icon>fa-undo</Icon>
    </Customlink>
    
    <Customlink sort="2">
        <Name>Statistiken</Name>
        <Filename>Statistiken</Filename>
        <Icon>fa-chart-bar</Icon>
    </Customlink>
</AdminMenu>

<Settingslink sort="3">
    <Name>Einstellungen</Name>
    <Icon>fa-cog</Icon>
    <!-- Settings siehe Abschnitt 5 -->
</Settingslink>
```

**Rendering in Bootstrap:**
```php
public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
{
    switch ($tabName) {
        case 'Retouren-Übersicht':
            return $this->renderOverview($smarty);
            
        case 'Statistiken':
            return $this->renderStatistics($smarty);
            
        default:
            return '';
    }
}
```

---

### 3.2 Dashboard-Widget (Optional)

**Ziel:** RMA-KPIs im Admin-Dashboard

**Hook:** `admin.dashboard.widgets`

```php
$dispatcher->listen('admin.dashboard.widgets', function($event) {
    $service = Shop::Container()->get(Services\ReturnRequestService::class);
    
    $stats = [
        'open' => $service->getOpenRMAsCount(),
        'today' => $service->getTodayRMAsCount(),
        'thisWeek' => $service->getWeekRMAsCount()
    ];
    
    $event['widgets'][] = [
        'title' => 'Retouren',
        'icon' => 'fa-undo',
        'content' => $this->renderDashboardWidget($stats),
        'priority' => 50
    ];
});
```

**Widget-Template:**
```html
<div class="dashboard-widget rma-widget">
    <h3><i class="fa fa-undo"></i> Retouren</h3>
    <ul class="stats-list">
        <li>
            <span class="label">Offen:</span>
            <span class="value">{$stats.open}</span>
        </li>
        <li>
            <span class="label">Heute:</span>
            <span class="value">{$stats.today}</span>
        </li>
        <li>
            <span class="label">Diese Woche:</span>
            <span class="value">{$stats.thisWeek}</span>
        </li>
    </ul>
    <a href="/admin/plugin/jtl_customer_returns/overview" class="btn btn-sm btn-primary">
        Alle Retouren →
    </a>
</div>
```

---

### 3.3 Bestelldetails erweitern

**Ziel:** RMA-Historie in Admin-Bestellansicht

**Hook:** `admin.order.details.tabs`

```php
$dispatcher->listen('admin.order.details.tabs', function($event) {
    $orderID = $event['orderID'];
    
    // RMAs für Bestellung laden
    $service = Shop::Container()->get(Services\ReturnRequestService::class);
    $rmas = $service->getRMAsByOrder($orderID);
    
    if (empty($rmas)) {
        return;
    }
    
    // Tab hinzufügen
    $event['tabs'][] = [
        'title' => 'Retouren (' . count($rmas) . ')',
        'content' => $this->renderOrderRMAsTab($rmas),
        'icon' => 'fa-undo'
    ];
});
```

**Tab-Content:**
```html
<table class="table table-striped">
    <thead>
        <tr>
            <th>RMA-Nr.</th>
            <th>Datum</th>
            <th>Status</th>
            <th>Items</th>
            <th>Wert</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
        {foreach $rmas as $rma}
        <tr>
            <td>{$rma->getRmaNr()}</td>
            <td>{$rma->getCreateDate()|date_format:"%d.%m.%Y %H:%M"}</td>
            <td><span class="badge badge-{$rma->getStatusColor()}">{$rma->getStatusText()}</span></td>
            <td>{$rma->getItems()->count()}</td>
            <td>{$rma->getTotalGross()|number_format:2} €</td>
            <td>
                <a href="/admin/plugin/jtl_customer_returns/edit?rmaID={$rma->getId()}" class="btn btn-sm btn-primary">
                    Details
                </a>
            </td>
        </tr>
        {/foreach}
    </tbody>
</table>
```

---

## 4. E-Mail-Templates

### 4.1 Template-Registrierung

**Definiert in:** `info.xml`

```xml
<Emailtemplate>
    <!-- 1. Retouren-Bestätigung -->
    <Template>
        <Name>Retouren-Bestätigung</Name>
        <Description>E-Mail bei RMA-Erstellung</Description>
        <Type>text/html</Type>
        <ModulId>jtl_customer_returns_confirmation</ModulId>
        <Active>Y</Active>
        <TemplateLanguage iso="GER">
            <Subject>Ihre Retoure {$rma_number} wurde registriert</Subject>
            <ContentHtml><![CDATA[...]]></ContentHtml>
            <ContentText><![CDATA[...]]></ContentText>
        </TemplateLanguage>
    </Template>
    
    <!-- 2. Status-Update -->
    <Template>
        <Name>Retouren-Status-Update</Name>
        <Description>E-Mail bei Status-Änderung</Description>
        <Type>text/html</Type>
        <ModulId>jtl_customer_returns_status_update</ModulId>
        <Active>Y</Active>
        <TemplateLanguage iso="GER">
            <Subject>Ihre Retoure {$rma_number} - Status: {$new_status}</Subject>
            <ContentHtml><![CDATA[...]]></ContentHtml>
        </TemplateLanguage>
    </Template>
    
    <!-- 3. Gutschein erstellt -->
    <Template>
        <Name>Retouren-Gutschein</Name>
        <Description>E-Mail bei Gutscheinerstellung</Description>
        <Type>text/html</Type>
        <ModulId>jtl_customer_returns_voucher</ModulId>
        <Active>Y</Active>
        <TemplateLanguage iso="GER">
            <Subject>Ihr Gutschein für Retoure {$rma_number}</Subject>
            <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Sehr geehrte/r {$customer_name},</p>
<p>Für Ihre Retoure <strong>{$rma_number}</strong> wurde ein Gutschein erstellt:</p>
<div class="voucher-box">
    <p><strong>Gutscheincode:</strong> {$voucher_code}</p>
    <p><strong>Wert:</strong> {$voucher_amount|number_format:2} €</p>
    <p><strong>Gültig bis:</strong> {$voucher_expires|date_format:"%d.%m.%Y"}</p>
</div>
<p>Sie können den Gutschein im Warenkorb einlösen.</p>
{includeMailTemplate template=footer type=html}
            ]]></ContentHtml>
        </TemplateLanguage>
    </Template>
    
    <!-- 4. Rückzahlung verarbeitet -->
    <Template>
        <Name>Retouren-Rückzahlung</Name>
        <Description>E-Mail bei Rückzahlung</Description>
        <Type>text/html</Type>
        <ModulId>jtl_customer_returns_refund</ModulId>
        <Active>Y</Active>
        <TemplateLanguage iso="GER">
            <Subject>Rückzahlung für Retoure {$rma_number} verarbeitet</Subject>
            <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Sehr geehrte/r {$customer_name},</p>
<p>Die Rückzahlung für Ihre Retoure <strong>{$rma_number}</strong> wurde verarbeitet.</p>
<ul>
    <li><strong>Betrag:</strong> {$refund_amount|number_format:2} €</li>
    <li><strong>Zahlungsmethode:</strong> {$payment_method}</li>
    <li><strong>Voraussichtliche Buchung:</strong> {$refund_date|date_format:"%d.%m.%Y"}</li>
</ul>
<p>Die Gutschrift wird in den nächsten 3-5 Werktagen auf Ihrem Konto eingehen.</p>
{includeMailTemplate template=footer type=html}
            ]]></ContentHtml>
        </TemplateLanguage>
    </Template>
</Emailtemplate>
```

---

### 4.2 E-Mail-Versand-Service

```php
namespace Plugin\jtl_customer_returns\Services;

use JTL\Mail\Mail\Mail;
use JTL\Shop;

class NotificationService
{
    /**
     * Bestätigungs-E-Mail senden
     */
    public function sendReturnConfirmation(int $rmaID): bool
    {
        $rma = $this->getRMA($rmaID);
        $customer = $this->getCustomer($rma->getCustomerID());
        
        $mail = new Mail();
        $mail->setToMail($customer->cMail);
        $mail->setToName($customer->cVorname . ' ' . $customer->cNachname);
        $mail->setSubject('Ihre Retoure ' . $rma->getRmaNr() . ' wurde registriert');
        
        // Smarty-Variablen
        $smarty = Shop::Container()->getSmarty();
        $smarty->assign('rma_number', $rma->getRmaNr());
        $smarty->assign('customer_name', $customer->cVorname . ' ' . $customer->cNachname);
        $smarty->assign('return_items', $this->formatRMAItems($rma->getItems()));
        $smarty->assign('return_address', $this->getShopReturnAddress());
        
        // Template rendern
        $mail->setBodyHTML($smarty->fetch('mail_templates/return_confirmation.tpl'));
        
        // Anhang: Retourenlabel (falls vorhanden)
        if ($rma->getLabelPath()) {
            $mail->addAttachment(PFAD_ROOT . $rma->getLabelPath(), 'Retourenlabel.pdf');
        }
        
        return $mail->send();
    }
    
    /**
     * Status-Update-E-Mail senden
     */
    public function sendStatusUpdate(int $rmaID, int $newStatus, string $comment = ''): bool
    {
        $rma = $this->getRMA($rmaID);
        $customer = $this->getCustomer($rma->getCustomerID());
        
        $statusTexts = [
            0 => 'Offen',
            1 => 'In Bearbeitung',
            2 => 'Angenommen',
            3 => 'Abgeschlossen',
            4 => 'Abgelehnt'
        ];
        
        $mail = new Mail();
        $mail->setToMail($customer->cMail);
        $mail->setSubject('Ihre Retoure ' . $rma->getRmaNr() . ' - Status: ' . $statusTexts[$newStatus]);
        
        $smarty = Shop::Container()->getSmarty();
        $smarty->assign('rma_number', $rma->getRmaNr());
        $smarty->assign('customer_name', $customer->cVorname . ' ' . $customer->cNachname);
        $smarty->assign('new_status', $statusTexts[$newStatus]);
        $smarty->assign('admin_comment', $comment);
        
        $mail->setBodyHTML($smarty->fetch('mail_templates/status_update.tpl'));
        
        return $mail->send();
    }
    
    /**
     * Gutschein-E-Mail senden
     */
    public function sendVoucherNotification(int $rmaID, string $voucherCode, float $amount): bool
    {
        $rma = $this->getRMA($rmaID);
        $customer = $this->getCustomer($rma->getCustomerID());
        
        $mail = new Mail();
        $mail->setToMail($customer->cMail);
        $mail->setSubject('Ihr Gutschein für Retoure ' . $rma->getRmaNr());
        
        $smarty = Shop::Container()->getSmarty();
        $smarty->assign('rma_number', $rma->getRmaNr());
        $smarty->assign('customer_name', $customer->cVorname . ' ' . $customer->cNachname);
        $smarty->assign('voucher_code', $voucherCode);
        $smarty->assign('voucher_amount', $amount);
        $smarty->assign('voucher_expires', date('Y-m-d', strtotime('+1 year')));
        
        $mail->setBodyHTML($smarty->fetch('mail_templates/voucher.tpl'));
        
        return $mail->send();
    }
}
```

---

## 5. Config-Optionen (Plugin-Settings)

### 5.1 Settings-Gruppen

**Definiert in:** `info.xml` (siehe 10_plugin_architecture.md)

**Kategorien:**

1. **Retourenbedingungen** (sort 1-9)
   - `return_period_days` (number, default: 14)
   - `auto_refund_shipping` (checkbox, default: on)

2. **Retourenlabel** (sort 10-19)
   - `auto_create_label` (checkbox, default: off)
   - `shipping_provider` (selectbox: dhl, dpd, ups, hermes)
   - `shipping_api_key` (text, default: '')

3. **Produktausschlüsse** (sort 20-29)
   - `excluded_categories` (text, comma-separated IDs)
   - `excluded_product_nos` (textarea, comma-separated)

4. **Benachrichtigungen** (sort 30-39)
   - `admin_notification_email` (text)
   - `notify_admin_on_new_rma` (checkbox, default: on)

5. **Wawi-Sync** (sort 40-49)
   - `wawi_sync_interval` (number, default: 15 minutes)
   - `wawi_auto_sync` (checkbox, default: on)

6. **Rücksendeadresse** (sort 50-59)
   - `shop_return_address` (textarea, multiline)

---

### 5.2 Config-Zugriff im Code

```php
// In Services/Controllers
$config = $this->getPlugin()->getConfig();

// Einzelwerte abrufen
$returnPeriodDays = (int)$config->getValue('return_period_days');
$autoCreateLabel = $config->getValue('auto_create_label') === 'Y';
$shippingProvider = $config->getValue('shipping_provider');

// Alle Settings als Array
$allSettings = $config->getOptions();
```

---

### 5.3 Dynamische Config-Validierung

```php
// In Bootstrap.php
public function saveAdminMenuTab(int $tabID, array $postData): bool
{
    // Vor dem Speichern validieren
    if (isset($postData['return_period_days'])) {
        $days = (int)$postData['return_period_days'];
        
        if ($days < 1 || $days > 365) {
            Shop::Container()->getAlertService()->addError(
                'Retourenfrist muss zwischen 1 und 365 Tagen liegen.',
                'return_period_days'
            );
            return false;
        }
    }
    
    // Versanddienstleister-API-Key validieren
    if ($postData['auto_create_label'] === 'Y' && empty($postData['shipping_api_key'])) {
        Shop::Container()->getAlertService()->addError(
            'API-Key ist erforderlich, wenn automatische Label-Erstellung aktiviert ist.',
            'shipping_api_key'
        );
        return false;
    }
    
    return parent::saveAdminMenuTab($tabID, $postData);
}
```

---

## 6. Wawi-Synchronisation (dbeS)

### 6.1 Sync-Queue-Integration

**Trigger:** RMA-Erstellung oder Status-Änderung

```php
// In Services/SyncService.php
public function syncToWawi(RMADomainObject $rma): bool
{
    // Sync-Objekt erstellen
    $syncObject = $this->createRMASyncObject($rma);
    
    // In dbeS-Queue einreihen
    $queue = Shop::Container()->get('dbes.queue');
    $queue->add('rma', $syncObject);
    
    // RMA als synchronisiert markieren
    $rma->setSynced(1);
    $rma->setLastSyncDate(date('Y-m-d H:i:s'));
    $this->repository->save($rma);
    
    return true;
}

/**
 * RMA-Sync-Objekt erstellen
 */
private function createRMASyncObject(RMADomainObject $rma): RMASyncObject
{
    $syncObject = new RMASyncObject();
    $syncObject->setId($rma->getId());
    $syncObject->setRmaNr($rma->getRmaNr());
    $syncObject->setOrderID($rma->getOrderID());
    $syncObject->setCustomerID($rma->getCustomerID());
    $syncObject->setStatus($rma->getStatus());
    
    // Items hinzufügen
    foreach ($rma->getItems() as $item) {
        $itemSync = new RMAItemSyncObject();
        $itemSync->setProductID($item->getProductID());
        $itemSync->setQuantity($item->getQuantity());
        $itemSync->setReasonID($item->getReasonID());
        $syncObject->addItem($itemSync);
    }
    
    // Rücksendeadresse
    if ($rma->getReturnAddressID()) {
        $address = $this->addressService->getAddressById($rma->getReturnAddressID());
        $addressSync = new RMAAddressSyncObject();
        $addressSync->fromDomainObject($address);
        $syncObject->setAddress($addressSync);
    }
    
    return $syncObject;
}
```

---

### 6.2 Bidirektionale Sync (Wawi → Shop)

**Wawi sendet Update zurück:**

```php
// In Bootstrap.php -> boot()
$dispatcher->listen('dbes.sync.received', function($event) {
    if ($event['type'] !== 'rma') {
        return;
    }
    
    $data = $event['data'];
    $rmaID = $data['id'] ?? 0;
    
    if (!$rmaID) {
        return;
    }
    
    // RMA aktualisieren
    $service = Shop::Container()->get(Services\ReturnRequestService::class);
    $rma = $service->getRMA($rmaID);
    
    // Status von Wawi übernehmen
    if (isset($data['status'])) {
        $rma->setStatus((int)$data['status']);
    }
    
    // Wawi-ID speichern
    if (isset($data['wawiID'])) {
        $rma->setWawiID((int)$data['wawiID']);
    }
    
    $service->save($rma);
    
    // History-Event
    $historyService = Shop::Container()->get(RMAHistoryService::class);
    $historyService->addEvent($rmaID, 'WAWI_UPDATED', [
        'status' => $data['status'] ?? null,
        'wawiID' => $data['wawiID'] ?? null
    ]);
});
```

---

## 7. Cron-Job-Integration

### 7.1 Cron-Job-Registrierung

**In Bootstrap->registerCronJobs():**

```php
private function registerCronJobs(): void
{
    $db = Shop::Container()->getDB();
    $config = $this->getPlugin()->getConfig();
    
    $syncInterval = (int)$config->getValue('wawi_sync_interval');
    
    // RMA-Wawi-Sync Job
    $cronID = $db->insert('tcron', (object)[
        'name' => 'RMA Wawi Sync',
        'jobType' => 'plugin',
        'frequency' => $syncInterval, // Minuten
        'startDate' => date('Y-m-d H:i:s'),
        'nextStart' => date('Y-m-d H:i:s', strtotime("+{$syncInterval} minutes")),
        'class' => 'Plugin\\jtl_customer_returns\\Crons\\RMASyncCronJob',
        'tab' => 'plugins',
        'isActive' => $config->getValue('wawi_auto_sync') === 'Y' ? 1 : 0
    ]);
    
    // Cron-ID in Plugin-Meta speichern
    $db->insert('tplugineinstellungen', (object)[
        'kPlugin' => $this->getPlugin()->getID(),
        'cName' => 'rma_sync_cron_id',
        'cWert' => $cronID
    ]);
}
```

---

### 7.2 Cron-Job-Implementierung

```php
// Crons/RMASyncCronJob.php
namespace Plugin\jtl_customer_returns\Crons;

use JTL\Cron\Job;
use JTL\Cron\QueueEntry;
use JTL\Shop;

class RMASyncCronJob extends Job
{
    public function start(QueueEntry $queueEntry): JobInterface
    {
        $logger = $this->logger;
        $logger->info('RMA Wawi Sync Job started');
        
        // Unsynchronisierte RMAs laden
        $repo = Shop::Container()->get(RMARepository::class);
        $rmas = $repo->getReturns(1, ['synced' => 0], 100);
        
        $syncService = Shop::Container()->get(Services\SyncService::class);
        $syncedCount = 0;
        $errorCount = 0;
        
        foreach ($rmas as $rma) {
            try {
                $success = $syncService->syncToWawi($rma);
                
                if ($success) {
                    $syncedCount++;
                    $logger->info('RMA ' . $rma->getRmaNr() . ' synced successfully');
                } else {
                    $errorCount++;
                    $logger->error('RMA ' . $rma->getRmaNr() . ' sync failed');
                }
            } catch (\Exception $e) {
                $errorCount++;
                $logger->error('RMA ' . $rma->getRmaNr() . ' exception: ' . $e->getMessage());
            }
        }
        
        $logger->info("RMA Sync Job completed. Synced: $syncedCount, Errors: $errorCount");
        
        // Admin benachrichtigen bei Fehlern
        if ($errorCount > 0) {
            $this->notifyAdminAboutSyncErrors($errorCount);
        }
        
        return $this;
    }
    
    /**
     * Admin bei Sync-Fehlern benachrichtigen
     */
    private function notifyAdminAboutSyncErrors(int $errorCount): void
    {
        $config = $this->getPlugin()->getConfig();
        $adminEmail = $config->getValue('admin_notification_email');
        
        if (empty($adminEmail)) {
            return;
        }
        
        $mail = new Mail();
        $mail->setToMail($adminEmail);
        $mail->setSubject('RMA Wawi Sync: ' . $errorCount . ' Fehler');
        $mail->setBodyHTML("
            <p>Beim automatischen Wawi-Sync sind {$errorCount} Fehler aufgetreten.</p>
            <p>Bitte prüfen Sie die Logs im Admin-Bereich.</p>
        ");
        $mail->send();
    }
}
```

---

## 8. Performance-Optimierungen

### 8.1 Caching

```php
// In Services/ReturnRequestService.php
public function getReturnableProducts(int $customerID, int $langID, int $returnPeriod): array
{
    $cache = Shop::Container()->getCache();
    $cacheKey = "rma_returnable_products_{$customerID}_{$langID}_{$returnPeriod}";
    
    // Cache-Check (TTL: 5 Minuten)
    if ($cache->has($cacheKey)) {
        return $cache->get($cacheKey);
    }
    
    // DB-Query
    $products = $this->repository->getReturnableProductsForCustomer(
        $customerID,
        $langID,
        $returnPeriod
    );
    
    // In Cache speichern
    $cache->set($cacheKey, $products, 300); // 5 Minuten
    
    return $products;
}
```

---

### 8.2 Lazy Loading

```php
// In RMADomainObject
private ?Collection $items = null;

public function getItems(): Collection
{
    if ($this->items === null) {
        $itemRepo = Shop::Container()->get(RMAItemRepository::class);
        $this->items = $itemRepo->getItemsByRMA($this->id);
    }
    
    return $this->items;
}
```

---

## Zusammenfassung

### ✅ Dokumentierte Integrationspunkte

| Kategorie | Anzahl | Details |
|-----------|--------|---------|
| **Events** | 4 | shop.order.shipped, shop.customer.deleted, shop.rma.created, shop.rma.status_changed |
| **Frontend-Hooks** | 3 | Kundenkonto-Menü, Bestelldetails, Footer |
| **Admin-Menü** | 3 | Übersicht, Statistiken, Einstellungen |
| **E-Mail-Templates** | 4 | Bestätigung, Status-Update, Gutschein, Rückzahlung |
| **Config-Optionen** | 11 | 6 Kategorien (Bedingungen, Label, Ausschlüsse, Benachrichtigungen, Sync, Adresse) |
| **Wawi-Sync** | 2 | Shop → Wawi (Queue), Wawi → Shop (bidirektional) |
| **Cron-Jobs** | 1 | RMASyncCronJob (alle 15 Min.) |

### ⏭️ Nächster Schritt: Todo 12

**Risikoanalyse durchführen** - Performance, Race Conditions, Migration-Fallstricke, Mitigation-Strategien

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** **25+ Integrationspunkte** dokumentiert. **4 Shop-Events**, **3 Frontend-Hooks**, **4 E-Mail-Templates**, **11 Config-Optionen**, **bidirektionale Wawi-Sync**, **Cron-Job-System**.
