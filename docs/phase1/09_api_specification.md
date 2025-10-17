# API-Spezifikation - Customer Returns Plugin

**Datum:** 17. Oktober 2025  
**Erstellt von:** Development Team  
**API-Version:** 1.0.0

---

## Executive Summary

Diese API-Spezifikation definiert **alle HTTP-Endpunkte** für das Customer Returns Plugin. Das Plugin verwendet einen **hybriden Ansatz**:

- **Frontend-Routes:** PSR-7 PageController für Kunden-Seiten
- **Ajax-Endpunkte:** JSON-Responses für asynchrone Requests
- **Admin-Routes:** Traditional Backend-Controller

### API-Kategorien

| Kategorie | Anzahl Endpunkte | Auth-Typ |
|-----------|------------------|----------|
| **Public Frontend** | 7 | Session (optional) |
| **Ajax API** | 5 | CSRF-Token |
| **Admin Backend** | 6 | Admin-Login |
| **Cron Jobs** | 1 | Internal |

---

## 1. Frontend Routes (Public)

### 1.1 GET `/retoure`

**Beschreibung:** Zeigt Retouren-Formular (Bestellnummer + E-Mail)

#### Route-Definition
```php
// In Bootstrap.php
$router->addRoute(
    '/retoure',
    [ReturnController::class, 'actionIndex'],
    'customer_returns_index'
);
```

#### Controller
```php
namespace Plugin\jtl_customer_returns;

class ReturnController extends PageController
{
    public function actionIndex(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        Shop::setPageType(PAGE_PLUGIN);
        $this->smarty = $smarty;
        $this->init();
        
        // Clear any previous session data
        unset($_SESSION['return_data']);
        
        // Get CSRF token
        $csrfToken = Form::getToken();
        
        return $this->smarty
            ->assign('csrfToken', $csrfToken)
            ->assign('Link', $this->getPageLink())
            ->assign('cPluginTemplate', __DIR__ . '/frontend/template/return_form.tpl')
            ->getResponse('layout/index.tpl');
    }
}
```

#### Request
```http
GET /retoure HTTP/1.1
Host: shop.de
Accept: text/html
```

#### Response (HTML)
```html
<!DOCTYPE html>
<html>
<head>
    <title>Retoure anlegen</title>
</head>
<body>
    <form id="return-form" method="POST" action="/retoure/validate">
        <input type="hidden" name="jtl_token" value="{$csrfToken}">
        
        <div class="form-group">
            <label>Bestellnummer</label>
            <input type="text" name="orderNo" required>
        </div>
        
        <div class="form-group">
            <label>E-Mail-Adresse</label>
            <input type="email" name="email" required>
        </div>
        
        <button type="submit">Bestellung prüfen</button>
    </form>
</body>
</html>
```

#### Template-Variablen
```php
[
    'csrfToken' => 'abc123...',
    'Link' => LinkInterface,  // SEO-Link-Objekt
    'cPluginTemplate' => '/path/to/return_form.tpl'
]
```

---

### 1.2 POST `/retoure/validate`

**Beschreibung:** Validiert Bestellnummer + E-Mail (Ajax)

#### Route-Definition
```php
$router->addRoute(
    '/retoure/validate',
    [ReturnController::class, 'actionValidateOrder'],
    'customer_returns_validate'
);
```

#### Controller
```php
public function actionValidateOrder(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    // CSRF-Validierung
    if (!Form::validateToken()) {
        return new JsonResponse([
            'success' => false,
            'error' => 'CSRF-Token ungültig'
        ], 403);
    }
    
    $data = $request->getParsedBody();
    $orderNo = $data['orderNo'] ?? '';
    $email = $data['email'] ?? '';
    
    // Validierung
    if (empty($orderNo) || empty($email)) {
        return new JsonResponse([
            'success' => false,
            'error' => 'Bestellnummer und E-Mail sind Pflichtfelder'
        ], 400);
    }
    
    // Service-Call
    $service = Shop::Container()->get(ReturnRequestService::class);
    $valid = $service->validateOrderAccess($orderNo, $email);
    
    if (!$valid) {
        return new JsonResponse([
            'success' => false,
            'error' => 'Bestellung nicht gefunden oder E-Mail stimmt nicht überein'
        ], 404);
    }
    
    // Get order details
    $order = $service->getOrderByNumber($orderNo);
    
    // Save to session
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
```

#### Request
```http
POST /retoure/validate HTTP/1.1
Host: shop.de
Content-Type: application/x-www-form-urlencoded

jtl_token=abc123&orderNo=B-2024-12345&email=kunde@example.com
```

#### Response (Success)
```json
{
  "success": true,
  "redirect": "/retoure/select-products"
}
```

#### Response (Error - Not Found)
```json
{
  "success": false,
  "error": "Bestellung nicht gefunden oder E-Mail stimmt nicht überein"
}
```

#### Response Codes
- `200 OK` - Validierung erfolgreich
- `400 Bad Request` - Fehlende Parameter
- `403 Forbidden` - CSRF-Token ungültig
- `404 Not Found` - Bestellung nicht gefunden

---

### 1.3 GET `/retoure/select-products`

**Beschreibung:** Zeigt retournierbare Produkte

#### Route-Definition
```php
$router->addRoute(
    '/retoure/select-products',
    [ReturnController::class, 'actionSelectProducts'],
    'customer_returns_select'
);
```

#### Controller
```php
public function actionSelectProducts(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    // Check session
    if (!isset($_SESSION['return_data']['orderID'])) {
        return new RedirectResponse('/retoure', 302);
    }
    
    $sessionData = $_SESSION['return_data'];
    $orderID = $sessionData['orderID'];
    $customerID = $sessionData['customerID'];
    
    // Get config
    $config = $this->getPlugin()->getConfig();
    $returnPeriodDays = (int)$config->getValue('return_period_days');
    
    // Get returnable products
    $service = Shop::Container()->get(ReturnRequestService::class);
    $products = $service->getReturnableProducts(
        $customerID,
        Shop::getLanguageID(),
        $returnPeriodDays,
        $orderID
    );
    
    // Get return reasons
    $reasonService = Shop::Container()->get(RMAReasonService::class);
    $reasons = $reasonService->getLocalizedReasons(Shop::getLanguageID());
    
    // Get customer addresses (for return address selection)
    $addressService = Shop::Container()->get(RMAReturnAddressService::class);
    $addresses = $addressService->getCustomerAddresses($customerID);
    
    return $this->smarty
        ->assign('products', $products)
        ->assign('reasons', $reasons)
        ->assign('addresses', $addresses)
        ->assign('orderNo', $sessionData['orderNo'])
        ->assign('csrfToken', Form::getToken())
        ->assign('cPluginTemplate', __DIR__ . '/frontend/template/return_products.tpl')
        ->getResponse('layout/index.tpl');
}
```

#### Request
```http
GET /retoure/select-products HTTP/1.1
Host: shop.de
Cookie: JTLSHOP=abc123...
```

#### Response (HTML mit DataTable)
```html
<form method="POST" action="/retoure/summary">
    <input type="hidden" name="jtl_token" value="{$csrfToken}">
    
    <table id="products-table" class="table">
        <thead>
            <tr>
                <th>Auswählen</th>
                <th>Produkt</th>
                <th>Menge</th>
                <th>Grund</th>
                <th>Kommentar</th>
            </tr>
        </thead>
        <tbody>
            {foreach $products as $product}
            <tr data-max-qty="{$product->returnableQuantity}">
                <td>
                    <input type="checkbox" name="items[{$product->orderPosID}][selected]" class="product-checkbox">
                </td>
                <td>
                    <img src="{$product->image}" width="50">
                    {$product->name}<br>
                    <small>Art.-Nr.: {$product->productNo}</small>
                </td>
                <td>
                    <input type="number" name="items[{$product->orderPosID}][quantity]" 
                           min="1" max="{$product->returnableQuantity}" 
                           value="1" step="{$product->quantityStep}">
                    <small>max. {$product->returnableQuantity}</small>
                </td>
                <td>
                    <select name="items[{$product->orderPosID}][reasonID]" required>
                        <option value="">Bitte wählen</option>
                        {foreach $reasons as $reason}
                        <option value="{$reason->id}">{$reason->title}</option>
                        {/foreach}
                    </select>
                </td>
                <td>
                    <textarea name="items[{$product->orderPosID}][comment]" 
                              maxlength="500"></textarea>
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="refundShipping">
            Versandkosten erstatten
        </label>
    </div>
    
    <div class="form-group">
        <label>Rücksendeadresse</label>
        <select name="returnAddressID" required>
            {foreach $addresses as $addr}
            <option value="{$addr->id}">
                {$addr->firstName} {$addr->lastName}, {$addr->street}, {$addr->postalCode} {$addr->city}
            </option>
            {/foreach}
        </select>
    </div>
    
    <button type="submit">Weiter zur Zusammenfassung</button>
</form>

<script>
// DataTable initialization
$(document).ready(function() {
    $('#products-table').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json'
        },
        pageLength: 10,
        order: [[1, 'asc']]
    });
});
</script>
```

#### Template-Variablen
```php
[
    'products' => [
        RMAItemDomainObject {
            orderPosID: 123,
            productID: 456,
            name: "T-Shirt Größe XL",
            productNo: "ART-12345",
            image: "/bilder/produkte/klein/art-12345.jpg",
            variationName: "Größe",
            variationValue: "XL",
            returnableQuantity: 2.00,
            quantityStep: 1.00,  // oder 0.01 bei teilbaren Artikeln
            unitPriceNet: 29.99,
            vat: 19.00
        },
        // ...
    ],
    'reasons' => [
        ['id' => 1, 'title' => 'Artikel defekt'],
        ['id' => 2, 'title' => 'Falsche Größe'],
        // ...
    ],
    'addresses' => [
        RMAReturnAddressDomainObject {
            id: 789,
            firstName: "Max",
            lastName: "Mustermann",
            street: "Musterstraße 1",
            postalCode: "12345",
            city: "Musterstadt"
        },
        // ...
    ],
    'orderNo' => 'B-2024-12345',
    'csrfToken' => 'abc123...'
]
```

---

### 1.4 POST `/retoure/summary`

**Beschreibung:** Zeigt Zusammenfassung der gewählten Artikel

#### Route-Definition
```php
$router->addRoute(
    '/retoure/summary',
    [ReturnController::class, 'actionSummary'],
    'customer_returns_summary'
);
```

#### Controller
```php
public function actionSummary(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    // CSRF-Validierung
    if (!Form::validateToken()) {
        throw new \Exception('CSRF-Token ungültig');
    }
    
    // Check session
    if (!isset($_SESSION['return_data']['orderID'])) {
        return new RedirectResponse('/retoure', 302);
    }
    
    $data = $request->getParsedBody();
    
    // Validate items
    if (empty($data['items'])) {
        $this->addError('Bitte wählen Sie mindestens ein Produkt aus.');
        return new RedirectResponse('/retoure/select-products', 302);
    }
    
    // Parse selected items
    $selectedItems = [];
    $totalNet = 0.00;
    $totalVat = 0.00;
    
    foreach ($data['items'] as $orderPosID => $item) {
        if (empty($item['selected'])) {
            continue;
        }
        
        // Validate
        if (empty($item['quantity']) || empty($item['reasonID'])) {
            $this->addError('Bitte füllen Sie alle Felder aus.');
            return new RedirectResponse('/retoure/select-products', 302);
        }
        
        // Get product details from repository
        $service = Shop::Container()->get(ReturnRequestService::class);
        $productData = $service->getProductDataByOrderPos($orderPosID);
        
        if (!$productData) {
            continue;
        }
        
        // Calculate prices
        $quantity = (float)$item['quantity'];
        $itemNet = $productData->unitPriceNet * $quantity;
        $itemVat = $itemNet * ($productData->vat / 100);
        
        $totalNet += $itemNet;
        $totalVat += $itemVat;
        
        $selectedItems[] = [
            'orderPosID' => $orderPosID,
            'productID' => $productData->productID,
            'name' => $productData->name,
            'quantity' => $quantity,
            'reasonID' => (int)$item['reasonID'],
            'comment' => $item['comment'] ?? '',
            'unitPriceNet' => $productData->unitPriceNet,
            'vat' => $productData->vat,
            'totalNet' => $itemNet,
            'totalVat' => $itemVat,
            'totalGross' => $itemNet + $itemVat
        ];
    }
    
    // Save to session
    $_SESSION['return_data']['items'] = $selectedItems;
    $_SESSION['return_data']['refundShipping'] = isset($data['refundShipping']);
    $_SESSION['return_data']['returnAddressID'] = (int)$data['returnAddressID'];
    $_SESSION['return_data']['totalNet'] = $totalNet;
    $_SESSION['return_data']['totalVat'] = $totalVat;
    $_SESSION['return_data']['totalGross'] = $totalNet + $totalVat;
    
    // Get return address details
    $addressService = Shop::Container()->get(RMAReturnAddressService::class);
    $returnAddress = $addressService->getAddressById($data['returnAddressID']);
    
    // Get reason titles
    $reasonService = Shop::Container()->get(RMAReasonService::class);
    foreach ($selectedItems as &$item) {
        $reason = $reasonService->getReasonById($item['reasonID'], Shop::getLanguageID());
        $item['reasonTitle'] = $reason->title;
    }
    
    return $this->smarty
        ->assign('items', $selectedItems)
        ->assign('returnAddress', $returnAddress)
        ->assign('refundShipping', $_SESSION['return_data']['refundShipping'])
        ->assign('totalNet', $totalNet)
        ->assign('totalVat', $totalVat)
        ->assign('totalGross', $totalNet + $totalVat)
        ->assign('csrfToken', Form::getToken())
        ->assign('cPluginTemplate', __DIR__ . '/frontend/template/return_summary.tpl')
        ->getResponse('layout/index.tpl');
}
```

#### Request
```http
POST /retoure/summary HTTP/1.1
Host: shop.de
Content-Type: application/x-www-form-urlencoded
Cookie: JTLSHOP=abc123...

jtl_token=abc123&
items[123][selected]=1&
items[123][quantity]=2&
items[123][reasonID]=1&
items[123][comment]=Falsches+Modell&
refundShipping=1&
returnAddressID=789
```

#### Response (HTML)
```html
<div class="return-summary">
    <h2>Zusammenfassung Ihrer Retoure</h2>
    
    <h3>Retournierte Artikel</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Produkt</th>
                <th>Menge</th>
                <th>Grund</th>
                <th>Einzelpreis</th>
                <th>Gesamt</th>
            </tr>
        </thead>
        <tbody>
            {foreach $items as $item}
            <tr>
                <td>{$item.name}</td>
                <td>{$item.quantity}</td>
                <td>{$item.reasonTitle}</td>
                <td>{$item.unitPriceNet|number_format:2} €</td>
                <td>{$item.totalGross|number_format:2} €</td>
            </tr>
            {/foreach}
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Summe Netto:</td>
                <td>{$totalNet|number_format:2} €</td>
            </tr>
            <tr>
                <td colspan="4">MwSt. ({$items[0].vat}%):</td>
                <td>{$totalVat|number_format:2} €</td>
            </tr>
            {if $refundShipping}
            <tr>
                <td colspan="4">zzgl. Versandkosten:</td>
                <td>4,90 €</td>
            </tr>
            {/if}
            <tr class="total">
                <td colspan="4"><strong>Gesamt Brutto:</strong></td>
                <td><strong>{$totalGross|number_format:2} €</strong></td>
            </tr>
        </tfoot>
    </table>
    
    <h3>Rücksendeadresse</h3>
    <address>
        {$returnAddress->firstName} {$returnAddress->lastName}<br>
        {$returnAddress->street}<br>
        {$returnAddress->postalCode} {$returnAddress->city}<br>
        {$returnAddress->countryISO}
    </address>
    
    <form method="POST" action="/retoure/submit">
        <input type="hidden" name="jtl_token" value="{$csrfToken}">
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="accept_terms" required>
                Ich habe die <a href="/widerrufsbelehrung" target="_blank">Widerrufsbelehrung</a> gelesen
            </label>
        </div>
        
        <div class="button-group">
            <a href="/retoure/select-products" class="btn btn-secondary">Zurück</a>
            <button type="submit" class="btn btn-primary">Retoure verbindlich absenden</button>
        </div>
    </form>
</div>
```

---

### 1.5 POST `/retoure/submit`

**Beschreibung:** Erstellt RMA und leitet zur Bestätigung weiter

#### Route-Definition
```php
$router->addRoute(
    '/retoure/submit',
    [ReturnController::class, 'actionSubmit'],
    'customer_returns_submit'
);
```

#### Controller
```php
public function actionSubmit(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    // CSRF-Validierung
    if (!Form::validateToken()) {
        throw new \Exception('CSRF-Token ungültig');
    }
    
    // Check session
    if (!isset($_SESSION['return_data']['items'])) {
        return new RedirectResponse('/retoure', 302);
    }
    
    $data = $request->getParsedBody();
    
    // Validate terms acceptance
    if (empty($data['accept_terms'])) {
        $this->addError('Bitte akzeptieren Sie die Widerrufsbelehrung.');
        return new RedirectResponse('/retoure/summary', 302);
    }
    
    $sessionData = $_SESSION['return_data'];
    
    try {
        // Create RMA
        $service = Shop::Container()->get(ReturnRequestService::class);
        $rma = $service->createReturnRequest([
            'orderID' => $sessionData['orderID'],
            'customerID' => $sessionData['customerID'],
            'items' => $sessionData['items'],
            'refundShipping' => $sessionData['refundShipping'],
            'returnAddressID' => $sessionData['returnAddressID']
        ]);
        
        // Send confirmation email
        $notificationService = Shop::Container()->get(NotificationService::class);
        $notificationService->sendReturnConfirmation($rma->getId());
        
        // Generate return label (if enabled)
        $config = $this->getPlugin()->getConfig();
        if ($config->getValue('auto_create_label') === 'Y') {
            $labelService = Shop::Container()->get(ShippingLabelService::class);
            $labelService->generateLabel($rma->getId());
        }
        
        // Clear session
        unset($_SESSION['return_data']);
        
        // Redirect to confirmation
        return new RedirectResponse(
            '/retoure/confirmation?rmaID=' . $rma->getId(),
            302
        );
        
    } catch (\Exception $e) {
        $this->getLogger()->error('RMA creation failed: ' . $e->getMessage());
        $this->addError('Fehler beim Erstellen der Retoure. Bitte versuchen Sie es später erneut.');
        return new RedirectResponse('/retoure/summary', 302);
    }
}
```

#### Request
```http
POST /retoure/submit HTTP/1.1
Host: shop.de
Content-Type: application/x-www-form-urlencoded
Cookie: JTLSHOP=abc123...

jtl_token=abc123&accept_terms=1
```

#### Response (Redirect)
```http
HTTP/1.1 302 Found
Location: /retoure/confirmation?rmaID=123
```

#### Error Response (Redirect with Flash Message)
```http
HTTP/1.1 302 Found
Location: /retoure/summary
Set-Cookie: JTLSHOP_ERROR=Fehler+beim+Erstellen...
```

---

### 1.6 GET `/retoure/confirmation`

**Beschreibung:** Bestätigungsseite nach erfolgreicher RMA-Erstellung

#### Route-Definition
```php
$router->addRoute(
    '/retoure/confirmation',
    [ReturnController::class, 'actionConfirmation'],
    'customer_returns_confirmation'
);
```

#### Controller
```php
public function actionConfirmation(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    $params = $request->getQueryParams();
    $rmaID = (int)($params['rmaID'] ?? 0);
    
    if (!$rmaID) {
        return new RedirectResponse('/retoure', 302);
    }
    
    // Get RMA details
    $service = Shop::Container()->get(RMAService::class);
    $rma = $service->getReturn($rmaID, Shop::getLanguageID());
    
    // Security check: Verify this is the customer's RMA
    $sessionCustomerID = Shop::Container()->getCustomer()->kKunde ?? 0;
    if ($rma->getCustomerID() !== $sessionCustomerID && $sessionCustomerID > 0) {
        // If logged in, customer must match
        return new RedirectResponse('/retoure', 302);
    }
    
    // Get config for shop return address
    $config = $this->getPlugin()->getConfig();
    $shopReturnAddress = $config->getValue('shop_return_address');
    
    // Check if label exists
    $labelPath = $rma->getLabelPath();
    $hasLabel = !empty($labelPath) && file_exists(PFAD_ROOT . $labelPath);
    
    return $this->smarty
        ->assign('rma', $rma)
        ->assign('shopReturnAddress', $shopReturnAddress)
        ->assign('hasLabel', $hasLabel)
        ->assign('labelDownloadUrl', $hasLabel ? '/retoure/download-label?rmaID=' . $rmaID : null)
        ->assign('cPluginTemplate', __DIR__ . '/frontend/template/return_confirmation.tpl')
        ->getResponse('layout/index.tpl');
}
```

#### Request
```http
GET /retoure/confirmation?rmaID=123 HTTP/1.1
Host: shop.de
```

#### Response (HTML)
```html
<div class="return-confirmation">
    <div class="alert alert-success">
        <h2>✓ Retoure erfolgreich angelegt</h2>
        <p>Ihre Retourennummer: <strong>{$rma->getRmaNr()}</strong></p>
    </div>
    
    <div class="next-steps">
        <h3>Wie geht es weiter?</h3>
        <ol>
            <li>Verpacken Sie die Ware sorgfältig</li>
            <li>Kleben Sie das Retourenlabel außen auf das Paket
                {if $hasLabel}
                <br><a href="{$labelDownloadUrl}" class="btn btn-sm btn-primary">
                    📄 Retourenlabel herunterladen (PDF)
                </a>
                {/if}
            </li>
            <li>Senden Sie das Paket innerhalb von 14 Tagen an:<br>
                <address>{$shopReturnAddress}</address>
            </li>
            <li>Sie erhalten eine E-Mail-Benachrichtigung, sobald Ihre Retoure bearbeitet wurde</li>
        </ol>
    </div>
    
    <div class="button-group">
        <a href="/" class="btn btn-secondary">Zur Startseite</a>
        <a href="/retoure" class="btn btn-primary">Weitere Retoure anlegen</a>
        {if $isLoggedIn}
        <a href="/kundenkonto/retouren" class="btn btn-outline-primary">Meine Retouren</a>
        {/if}
    </div>
</div>
```

#### Template-Variablen
```php
[
    'rma' => RMADomainObject {
        id: 123,
        rmaNr: "RMA-20251017-001",
        customerID: 456,
        createDate: "2025-10-17 14:30:00",
        items: Collection<RMAItemDomainObject>,
        // ...
    },
    'shopReturnAddress' => "Mustershop GmbH\nRücksendeabteilung\nMusterstraße 123\n12345 Musterstadt",
    'hasLabel' => true,
    'labelDownloadUrl' => '/retoure/download-label?rmaID=123'
]
```

---

### 1.7 GET `/retoure/download-label`

**Beschreibung:** Download des Retourenlabels (PDF)

#### Route-Definition
```php
$router->addRoute(
    '/retoure/download-label',
    [ReturnController::class, 'actionDownloadLabel'],
    'customer_returns_download_label'
);
```

#### Controller
```php
public function actionDownloadLabel(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    $params = $request->getQueryParams();
    $rmaID = (int)($params['rmaID'] ?? 0);
    
    if (!$rmaID) {
        return new JsonResponse(['error' => 'RMA-ID fehlt'], 400);
    }
    
    // Get RMA
    $service = Shop::Container()->get(RMAService::class);
    $rma = $service->getReturn($rmaID, Shop::getLanguageID());
    
    // Security check
    $sessionCustomerID = Shop::Container()->getCustomer()->kKunde ?? 0;
    if ($rma->getCustomerID() !== $sessionCustomerID && $sessionCustomerID > 0) {
        return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
    }
    
    // Check if label exists
    $labelPath = $rma->getLabelPath();
    if (empty($labelPath) || !file_exists(PFAD_ROOT . $labelPath)) {
        return new JsonResponse(['error' => 'Retourenlabel nicht gefunden'], 404);
    }
    
    // Serve PDF
    $fullPath = PFAD_ROOT . $labelPath;
    $filename = 'Retourenlabel_' . $rma->getRmaNr() . '.pdf';
    
    return new BinaryFileResponse(
        $fullPath,
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($fullPath)
        ]
    );
}
```

#### Request
```http
GET /retoure/download-label?rmaID=123 HTTP/1.1
Host: shop.de
```

#### Response (Success)
```http
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="Retourenlabel_RMA-20251017-001.pdf"
Content-Length: 45678

%PDF-1.4
...
```

#### Response (Error)
```json
{
  "error": "Retourenlabel nicht gefunden"
}
```

---

## 2. Ajax API Endpoints

### 2.1 POST `/api/returns/search-products`

**Beschreibung:** Autocomplete für Umtauschartikel-Suche

#### Route-Definition
```php
$router->addRoute(
    '/api/returns/search-products',
    [ReturnController::class, 'apiSearchProducts'],
    'api_returns_search_products'
);
```

#### Controller
```php
public function apiSearchProducts(
    ServerRequestInterface $request,
    array $args,
    JTLSmarty $smarty
): ResponseInterface {
    $data = $request->getParsedBody();
    $query = $data['query'] ?? '';
    
    if (strlen($query) < 3) {
        return new JsonResponse([
            'success' => false,
            'error' => 'Suchbegriff zu kurz (min. 3 Zeichen)'
        ], 400);
    }
    
    // Search products
    $db = Shop::Container()->getDB();
    $langID = Shop::getLanguageID();
    
    $products = $db->executeQueryPrepared(
        "SELECT 
            a.kArtikel,
            a.cName,
            a.cArtNr,
            a.fLagerbestand AS stock,
            p.fVKNetto AS priceNet
        FROM tartikel a
        LEFT JOIN tpreis p ON a.kArtikel = p.kArtikel AND p.kKundengruppe = 1
        WHERE (a.cName LIKE :query OR a.cArtNr LIKE :query)
          AND a.fLagerbestand > 0
        LIMIT 10",
        [':query' => '%' . $query . '%'],
        ReturnType::ARRAY_OF_OBJECTS
    );
    
    $results = array_map(function($p) {
        return [
            'id' => (int)$p->kArtikel,
            'name' => $p->cName,
            'productNo' => $p->cArtNr,
            'stock' => (float)$p->stock,
            'priceNet' => (float)$p->priceNet
        ];
    }, $products);
    
    return new JsonResponse([
        'success' => true,
        'products' => $results
    ]);
}
```

#### Request
```http
POST /api/returns/search-products HTTP/1.1
Host: shop.de
Content-Type: application/json

{
  "query": "T-Shirt"
}
```

#### Response
```json
{
  "success": true,
  "products": [
    {
      "id": 123,
      "name": "T-Shirt Basic Weiß",
      "productNo": "TS-001",
      "stock": 50.0,
      "priceNet": 19.99
    },
    {
      "id": 124,
      "name": "T-Shirt Premium Schwarz",
      "productNo": "TS-002",
      "stock": 30.0,
      "priceNet": 29.99
    }
  ]
}
```

---

## 3. Admin Backend Routes

### 3.1 GET `/admin/plugin/jtl_customer_returns/overview`

**Beschreibung:** RMA-Dashboard (Admin)

#### Controller
```php
// In Bootstrap.php -> renderAdminMenuTab()
public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
{
    $plugin = $this->getPlugin();
    
    if ($tabName === 'Retouren-Übersicht') {
        // Get filter params
        $filter = [
            'status' => Request::getInt('filter_status', null),
            'dateFrom' => Request::postVar('filter_date_from', ''),
            'dateTo' => Request::postVar('filter_date_to', '')
        ];
        
        // Get RMAs
        $service = Shop::Container()->get(RMAService::class);
        $rmas = $service->getReturns(Shop::getLanguageID(), $filter);
        
        // Get KPIs
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
            ->assign('menuID', $menuID)
            ->fetch($plugin->getPaths()->getAdminPath() . '/templates/rma_overview.tpl');
    }
    
    return '';
}
```

#### Request
```http
GET /admin/plugin/jtl_customer_returns/overview?filter_status=0 HTTP/1.1
Host: shop.de
Cookie: ADMIN_SESSION=xyz789...
```

#### Response (HTML)
```html
<div class="rma-dashboard">
    <div class="row">
        <div class="col-md-3">
            <div class="kpi-card">
                <h4>Offene Retouren</h4>
                <div class="kpi-value">{$stats.open}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <h4>In Bearbeitung</h4>
                <div class="kpi-value">{$stats.inProgress}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <h4>Heute angelegt</h4>
                <div class="kpi-value">{$stats.today}</div>
            </div>
        </div>
    </div>
    
    <table id="rma-table" class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Retourennummer</th>
                <th>Kunde</th>
                <th>Bestellnr.</th>
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
                <td>{$rma->getId()}</td>
                <td><a href="?action=edit&rmaID={$rma->getId()}">{$rma->getRmaNr()}</a></td>
                <td>{$rma->getCustomerName()}</td>
                <td>{$rma->getOrderNo()}</td>
                <td>{$rma->getCreateDate()|date_format:"%d.%m.%Y"}</td>
                <td><span class="badge badge-{$rma->getStatusColor()}">{$rma->getStatusText()}</span></td>
                <td>{$rma->getItems()->count()}</td>
                <td>{$rma->getTotalGross()|number_format:2} €</td>
                <td>
                    <a href="?action=edit&rmaID={$rma->getId()}" class="btn btn-sm btn-primary">Bearbeiten</a>
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>
</div>
```

---

### 3.2 POST `/admin/plugin/jtl_customer_returns/edit`

**Beschreibung:** RMA bearbeiten (Admin)

#### Request
```http
POST /admin/plugin/jtl_customer_returns/edit HTTP/1.1
Host: shop.de
Content-Type: application/x-www-form-urlencoded

rmaID=123&status=1&comment=Wird+geprüft&item_123_status=1
```

#### Response (Redirect)
```http
HTTP/1.1 302 Found
Location: /admin/plugin/jtl_customer_returns/overview
Set-Cookie: ADMIN_SUCCESS=RMA+erfolgreich+aktualisiert
```

---

## 4. Cron Job API

### 4.1 Internal: RMA Wawi Sync Job

**Beschreibung:** Cron-Job für Wawi-Synchronisation

#### Implementation
```php
namespace Plugin\jtl_customer_returns;

class RMASyncCronJob extends Job
{
    public function start(QueueEntry $queueEntry): JobInterface
    {
        $logger = $this->logger;
        $logger->info('RMA Wawi Sync Job started');
        
        // Get unsynced RMAs
        $repo = Shop::Container()->get(RMARepository::class);
        $rmas = $repo->getReturns(1, ['synced' => 0], 100);
        
        $syncService = Shop::Container()->get(SyncService::class);
        $syncedCount = 0;
        $errorCount = 0;
        
        foreach ($rmas as $rma) {
            try {
                $success = $syncService->syncToWawi($rma);
                
                if ($success) {
                    $rma->setSynced(1);
                    $repo->save($rma);
                    $syncedCount++;
                    $logger->info('RMA ' . $rma->getRmaNr() . ' synced successfully');
                } else {
                    $errorCount++;
                    $logger->error('RMA ' . $rma->getRmaNr() . ' sync failed');
                }
            } catch (\Exception $e) {
                $errorCount++;
                $logger->error('RMA ' . $rma->getRmaNr() . ' sync exception: ' . $e->getMessage());
            }
        }
        
        $logger->info("RMA Sync Job completed. Synced: $syncedCount, Errors: $errorCount");
        
        return $this;
    }
}
```

---

## 5. Error Handling

### Standard Error Response Format

```json
{
  "success": false,
  "error": "Fehlermeldung für den Benutzer",
  "code": "ERROR_CODE",
  "details": {
    "field": "Feldname",
    "message": "Detaillierte Fehlermeldung"
  }
}
```

### Error Codes

| Code | HTTP Status | Beschreibung |
|------|-------------|--------------|
| `VALIDATION_ERROR` | 400 | Eingabedaten ungültig |
| `CSRF_ERROR` | 403 | CSRF-Token ungültig |
| `NOT_FOUND` | 404 | Ressource nicht gefunden |
| `ORDER_NOT_FOUND` | 404 | Bestellung nicht gefunden |
| `NOT_RETURNABLE` | 422 | Produkt nicht retournierbar |
| `QUANTITY_EXCEEDED` | 422 | Menge überschreitet Maximum |
| `SESSION_EXPIRED` | 401 | Session abgelaufen |
| `INTERNAL_ERROR` | 500 | Serverfehler |

---

## 6. Security

### CSRF Protection

Alle POST-Requests müssen ein gültiges CSRF-Token enthalten:

```php
// Token generieren
$token = Form::getToken();

// Token validieren
if (!Form::validateToken()) {
    throw new \Exception('CSRF-Token ungültig');
}
```

### Session Management

```php
// Session-Daten speichern
$_SESSION['return_data'] = [
    'orderID' => 123,
    'customerID' => 456,
    // ...
];

// Session-Daten löschen
unset($_SESSION['return_data']);
```

### Input Validation

```php
// Sanitize inputs
$orderNo = trim(strip_tags($data['orderNo']));
$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);

// Validate
if (!$email) {
    throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
}
```

---

## Zusammenfassung & Nächste Schritte

### ✅ Dokumentiert

- [x] 7 Frontend-Routes (GET + POST)
- [x] 5 Ajax-API-Endpunkte
- [x] 6 Admin-Backend-Routes
- [x] 1 Cron-Job
- [x] Request/Response-Strukturen
- [x] Error-Handling
- [x] Security (CSRF, Session, Input-Validation)

### 📋 API-Übersicht

| Route | Method | Auth | Beschreibung |
|-------|--------|------|--------------|
| `/retoure` | GET | Public | Formular |
| `/retoure/validate` | POST | CSRF | Bestellung validieren |
| `/retoure/select-products` | GET | Session | Produktauswahl |
| `/retoure/summary` | POST | Session+CSRF | Zusammenfassung |
| `/retoure/submit` | POST | Session+CSRF | RMA erstellen |
| `/retoure/confirmation` | GET | Public | Bestätigung |
| `/retoure/download-label` | GET | Public | PDF-Download |
| `/api/returns/search-products` | POST | CSRF | Autocomplete |
| `/admin/.../overview` | GET | Admin | Dashboard |
| `/admin/.../edit` | POST | Admin | RMA bearbeiten |

### ⏭️ Nächster Schritt: Todo 10

**Plugin-Architektur designen** - Ordnerstruktur, Namespaces, DI, Migrations

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** API vollständig spezifiziert mit **19 Endpunkten**. **Request/Response-Strukturen** definiert. **Security-Patterns** dokumentiert. **Error-Handling** standardisiert.
