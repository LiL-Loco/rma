# GitHub Copilot Instructions: JTL Shop 5 Retouren-Plugin

## Projektkontext

Du arbeitest an einem **Retouren-Plugin für JTL Shop 5**, das Kunden ermöglicht, Retouren über Bestellnummer und E-Mail zu initiieren, Artikel auszuwählen, Retourengründe anzugeben und optional Umtauschartikel zu wählen. Die Retoure wird automatisch in der JTL-Wawi synchronisiert.

**Wichtig:** Im Ordner `jtl_rma_beispiel` befindet sich ein noch nicht finalisiertes Retourensystem von JTL, das als **Anschauungsmaterial und Referenz** dient. Nutze die dort vorhandenen Templates, Domain-Objekte und Services als Orientierung für die Implementierung.

Du benutzt context 7 um dir infos über JTL Shop 5 Plugins zu holen und wenn du dort nichts findest holst du Dir die Infos aus der Doku https://jtl-devguide.readthedocs.io/projects/jtl-shop/de/latest/shop_plugins/index.html
---

## Technologie-Stack

### Backend
- **PHP 7.4+** (JTL Shop 5 Kompatibilität)
- **JTL Shop Framework** (Plugin-Architektur)
- Nutze vorhandene RMA-Klassen:
  - `RMAService`
  - `RMARepository`
  - `RMAHistoryService`
  - `RMAReturnAddressService`
  - `RMAItemRepository`
  - `RMAReasonRepository`

### Frontend
- **Smarty Template Engine** (JTL-Standard)
- **Bootstrap 4** (Nova-Template)
- **jQuery** für Ajax-Requests
- **DataTables.js** für Produktlisten

### Datenbank
Nutze diese **vorhandenen Tabellen**:
- `rma` - Haupttabelle für Retouren
- `rma_items` - Retourenpositionen
- `rma_reasons` - Retourengründe
- `return_address` - Rücksendeadressen
- `rma_history` - Retouren-Historie

---

## Plugin-Struktur

```
plugins/
  └── jtl_customer_returns/
      ├── version/
      │   └── 100/
      │       ├── Bootstrap.php
      │       ├── frontend/
      │       │   ├── ReturnController.php
      │       │   └── templates/
      │       │       ├── return_form.tpl
      │       │       ├── return_products.tpl
      │       │       ├── return_summary.tpl
      │       │       └── return_confirmation.tpl
      │       ├── adminmenu/
      │       │   ├── AdminController.php
      │       │   └── templates/
      │       ├── Services/
      │       │   ├── ReturnRequestService.php
      │       │   ├── SyncService.php
      │       │   ├── ExchangeService.php
      │       │   └── NotificationService.php
      │       ├── Repositories/
      │       │   └── (Erweitere bestehende RMA-Repositories)
      │       └── sql/
      │           └── migrations/
      ├── info.xml
      └── Migrations/
```

---

## Code-Konventionen

### PHP
- **PSR-12** Coding Standard
- **Type Hints** verwenden für Parameter und Return-Types
- **DocBlocks** für alle Klassen, Methoden und Properties
- **Namespaces** nach JTL-Konvention: `Plugin\jtl_customer_returns\`
- **Dependency Injection** wo möglich
- **Try-Catch** für externe Calls (DB, Wawi-Sync)

### Smarty Templates
- **Variablennamen**: `{$variableName}` (camelCase)
- **Template-Vererbung** nutzen: `{extends file="layout.tpl"}`
- **Blöcke** für Überschreibbarkeit: `{block name="content"}...{/block}`
- **Escaping**: `{$text|escape:'html'}`

### JavaScript
- **ES5-Syntax** (jQuery-kompatibel)
- **Namespacing**: `JTLReturns.functionName()`
- **Ajax-Calls** über jQuery
- **Fehlerbehandlung** mit `.fail()` Callbacks

---

## Services: Methoden-Vorgaben

### ReturnRequestService
```php
class ReturnRequestService {
    public function validateOrderAccess(string $orderNo, string $email): bool;
    public function getReturnableProducts(int $orderId): array;
    public function createReturnRequest(array $data): RMADomainObject;
    public function getReturnReasons(): array;
}
```

### SyncService
```php
class SyncService {
    public function syncToWawi(RMADomainObject $rma): bool;
    public function createRMASyncObject(RMADomainObject $rma): RMASyncObject;
}
```

### NotificationService
```php
class NotificationService {
    public function sendReturnConfirmation(int $rmaId): bool;
    public function sendReturnLabel(int $rmaId): bool;
}
```

### ExchangeService (optional)
```php
class ExchangeService {
    public function getExchangeProducts(int $productId): array;
    public function processExchange(array $returnData): bool;
}
```

---

## Controller: Actions-Vorgaben

### ReturnController
```php
class ReturnController {
    public function actionIndex(): void;              // Zeigt Formular (Bestellnummer + E-Mail)
    public function actionValidateOrder(): void;      // Ajax: Bestellung validieren
    public function actionSelectProducts(): void;     // Produktauswahl-Seite
    public function actionSubmit(): void;             // Retoure abschicken
    public function actionConfirmation(): void;       // Bestätigungsseite
}
```

---

## Templates: Funktionale Anforderungen

### `return_form.tpl`
- Formular mit Feldern: Bestellnummer, E-Mail
- Client-seitige Validierung (jQuery)
- Ajax-Request an `actionValidateOrder()`

### `return_products.tpl`
- DataTable mit Produkten aus Bestellung
- Checkboxen für Artikelauswahl
- Mengenfelder (Number-Input)
- Dropdown für Retourengründe (aus `rma_reasons`)
- Optional: Umtauschartikel-Auswahl
- Kommentarfeld (Textarea)
- Rücksendeadresse (Dropdown aus Kundenkonten/Lieferadressen)

### `return_summary.tpl`
- Übersicht gewählter Artikel
- Retourengründe anzeigen
- Rücksendeadresse anzeigen
- Bestätigen-Button → Submit zu `actionSubmit()`

### `return_confirmation.tpl`
- Erfolgsmeldung
- Retourennummer anzeigen
- Informationen zum Ablauf
- Link zum Retourenlabel (falls vorhanden)

---

## Wawi-Integration: dbeS-Synchronisation

### Zu verwendende Sync-Objekte
- **RMASyncObject** (bereits vorhanden) - Hauptretoure
- **RMAAddressSyncObject** - Rücksendeadresse
- **RMAItemSyncObject** - Retourenpositionen

### Sync-Ablauf
1. Retoure im Shop erstellen → `RMADomainObject` speichern
2. `SyncService->syncToWawi()` aufrufen
3. Sync-Objekte erstellen und in dbeS-Queue einreihen
4. Status in `rma_history` loggen

### Fehlerbehandlung
- Logging bei Sync-Fehlern
- Retry-Mechanismus (3 Versuche)
- Admin-Benachrichtigung bei finalen Fehlschlägen

---

## E-Mail-System

### Bestätigungs-E-Mail
**Template-Platzhalter:**
- `{$rma_number}` - Retourennummer
- `{$customer_name}` - Kundenname
- `{$return_items}` - Array gewählter Artikel
- `{$return_address}` - Rücksendeadresse
- `{$order_number}` - Bestellnummer

### Retourenlabel-E-Mail
**Template-Platzhalter:**
- `{$rma_number}`
- `{$label_pdf}` - PDF-Anhang (Base64 oder Pfad)
- `{$shipping_instructions}` - Versandanleitung

---

## Admin-Bereich: Plugin-Einstellungen

### Konfigurationsoptionen
```php
[
    'return_period_days' => 14,              // Retourenzeitraum
    'auto_create_label' => true,             // Retourenlabel automatisch erstellen
    'enable_exchange' => false,              // Umtausch aktivieren
    'excluded_categories' => [],             // Ausgeschlossene Kategorien (IDs)
    'notification_emails' => 'admin@shop.de' // Admin-Benachrichtigungen
]
```

### Admin-Views
- **Retouren-Liste** (`rma_list.tpl` verwenden)
- **Retouren-Detail** (`rma_summary.tpl` anpassen)
- **Statistik-Dashboard** (neue View)

---

## Testing-Anforderungen

### Unit-Tests (PHPUnit)
- Service-Methoden testen
- Repository-CRUD-Operationen
- Validierungs-Logik

### Integrationstests
- Kompletten Retouren-Flow durchspielen:
  1. Formular → Validierung
  2. Produktauswahl → Submit
  3. Wawi-Sync → E-Mail-Versand
- Mock-Objekte für externe Abhängigkeiten (Wawi, E-Mail)

### Frontend-Tests
- Browser-Kompatibilität (Chrome, Firefox, Safari, Edge)
- Responsive Design (Mobile, Tablet, Desktop)
- JavaScript-Fehlerfreiheit (Console-Check)

---

## Häufige Aufgaben: Code-Beispiele

### Bestellung validieren
```php
public function validateOrderAccess(string $orderNo, string $email): bool {
    $order = Shop::Container()->getDB()->query(
        "SELECT kBestellung, cMail FROM tbestellung WHERE cBestellNr = :orderNo",
        [':orderNo' => $orderNo]
    )->fetch();
    
    return $order && strtolower($order->cMail) === strtolower($email);
}
```

### Retoure erstellen
```php
public function createReturnRequest(array $data): RMADomainObject {
    $rma = new RMADomainObject();
    $rma->setOrderId($data['order_id']);
    $rma->setCustomerId($data['customer_id']);
    $rma->setStatus('pending');
    $rma->setCreatedAt(date('Y-m-d H:i:s'));
    
    $repo = new RMARepository();
    $rmaId = $repo->save($rma);
    
    // Items speichern
    foreach ($data['items'] as $item) {
        $rmaItem = new RMAItemDomainObject();
        $rmaItem->setRmaId($rmaId);
        $rmaItem->setProductId($item['product_id']);
        $rmaItem->setQuantity($item['quantity']);
        $rmaItem->setReasonId($item['reason_id']);
        
        $itemRepo = new RMAItemRepository();
        $itemRepo->save($rmaItem);
    }
    
    return $rma;
}
```

### Ajax-Request (Frontend)
```javascript
function validateOrder() {
    var orderNo = $('#order_number').val();
    var email = $('#email').val();
    
    $.ajax({
        url: '/plugin/returns/validate',
        method: 'POST',
        data: { orderNo: orderNo, email: email },
        success: function(response) {
            if (response.valid) {
                window.location.href = '/plugin/returns/select-products';
            } else {
                alert('Bestellung nicht gefunden oder E-Mail stimmt nicht überein.');
            }
        },
        error: function() {
            alert('Fehler bei der Validierung.');
        }
    });
}
```

---

## Best Practices

### Sicherheit
- **Input-Validierung**: Alle Benutzereingaben validieren
- **SQL-Injection**: Prepared Statements verwenden
- **XSS**: Template-Escaping aktivieren (`{$var|escape:'html'}`)
- **CSRF**: Token in Formulare einbauen

### Performance
- **Caching**: Retourengründe cachen (selten ändernd)
- **DB-Queries**: JOINs statt N+1-Queries
- **Ajax**: Debouncing bei Autocomplete-Feldern

### Fehlerbehandlung
- **Try-Catch**: Bei allen externen Calls
- **Logging**: `Shop::Container()->getLogService()->error($message)`
- **User-Feedback**: Verständliche Fehlermeldungen

### Code-Qualität
- **DRY**: Wiederholende Logik in Services auslagern
- **SOLID**: Single Responsibility Principle beachten
- **Kommentare**: Komplexe Logik dokumentieren

---

## Workflow: Entwicklungsreihenfolge

1. **Phase 2**: Plugin-Grundstruktur (`info.xml`, `Bootstrap.php`)
2. **Phase 3**: Backend-Services (`ReturnRequestService`, `SyncService`)
3. **Phase 3**: Controller (`ReturnController`)
4. **Phase 4**: Templates (`return_form.tpl`, `return_products.tpl`, etc.)
5. **Phase 5**: Wawi-Integration (dbeS-Sync)
6. **Phase 6**: E-Mail-System
7. **Phase 7**: Admin-Bereich
8. **Phase 8**: Testing
9. **Phase 9**: Dokumentation

---

## Wichtige Hinweise

- **Nutze vorhandene RMA-Klassen** statt Neuimplementierung
- **Folge JTL-Konventionen** für Plugin-Struktur
- **Teste Wawi-Sync frühzeitig** (kritischer Pfad)
- **Mobile-First** bei Template-Entwicklung
- **Fehlerbehandlung** von Anfang an einbauen
- **Logging** für alle kritischen Operationen

---

Bei Fragen zur JTL-API, Smarty-Syntax oder dbeS-Synchronisation: **Siehe offizielle JTL-Dokumentation** oder frage nach konkreten Code-Beispielen.
