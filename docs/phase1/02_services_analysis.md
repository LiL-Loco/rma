# RMA Services - Code-Analyse

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** `jtl_rma_beispiel/RMA/Services/`

---

## Übersicht

Die RMA-Services bilden die **Business Logic Layer** zwischen Controllern und Repositories. Sie nutzen das **Repository Pattern** für Datenbankzugriffe und implementieren domänenspezifische Geschäftslogik.

### Verfügbare Services

1. **RMAService** - Kern-Service für Retouren-Verwaltung
2. **RMAHistoryService** - Event-Logging und Änderungsverfolgung
3. **RMAReturnAddressService** - Adress-Verwaltung
4. **RMAReasonService** - Retourengründe-Verwaltung

---

## 1. RMAService

**Zweck:** Zentrale Geschäftslogik für Retouren-Verwaltung

### Dependencies (Constructor Injection)

```php
__construct(
    private readonly RMARepository $RMARepository = new RMARepository(),
    private readonly RMAItemRepository $RMAItemRepository = new RMAItemRepository(),
    public RMAReturnAddressService $RMAReturnAddressService = new RMAReturnAddressService()
)
```

### Properties

| Property | Typ | Zweck |
|----------|-----|-------|
| `$rmas` | RMADomainObject[] | Cache für geladene Retouren |
| `$returnableProducts` | RMAItems | Cache für retournierbare Produkte |
| `$logService` | LogService | Logging (via LoggerTrait) |

### Kern-Methoden

#### Retouren laden und verwalten

```php
public function loadReturns(int $langID, array $filter = [], ?int $limit = null): self
```
- **Zweck:** Lädt Retouren aus DB mit optionalen Filtern
- **Parameter:**
  - `$langID` - Sprach-ID für lokalisierte Daten
  - `$filter` - Assoziatives Array (z.B. `['customerID' => 123]`)
  - `$limit` - Max. Anzahl Ergebnisse
- **Return:** `$this` (Fluent Interface)
- **Speichert in:** `$this->rmas` (Array mit ID als Key)

```php
public function getReturn(int $id, ?int $customerID = null, ?int $langID = null): RMADomainObject
```
- **Zweck:** Einzelne Retoure abrufen (mit Caching)
- **Logic:**
  1. Prüft Cache (`$this->rmas`)
  2. Falls nicht vorhanden → DB-Query mit Filter
  3. Return: RMA oder leeres DomainObject

```php
public function newReturn(int $customerID): RMADomainObject
```
- **Zweck:** Neue leere Retoure erstellen
- **Return:** RMADomainObject mit default values

#### Retournierbare Produkte

```php
public function getReturnableProducts(
    int $customerID = 0,
    int $languageID = 0,
    int $cancellationTime = 0,
    int $orderID = 0,
    bool $forceDB = false
): RMAItems
```
- **Zweck:** Produkte ermitteln, die retourniert werden können
- **Caching:** Nutzt `$this->returnableProducts` (außer `$forceDB = true`)
- **Business Logic:**
  - Defaults aus Session/Config (`Frontend::getCustomer()`, `Shop::getLanguageID()`)
  - `$cancellationTime` aus Shop-Einstellung `global_cancellation_time`
  - Filtern nach Retourenfrist
- **Return:** RMAItems Collection
- **Wichtig:** Basis für Produktauswahl-UI

```php
public function isOrderReturnable(int $orderID, ?RMAItems $returnableProducts = null): bool
```
- **Zweck:** Prüfen ob Bestellung retournierbar ist
- **Logic:**
  - Falls `$returnableProducts` gegeben → Suche in Collection
  - Sonst → DB-Query
- **Use Case:** Validierung vor Formular-Anzeige

#### Items verwalten

```php
public function getRMAItems(?array $rmas = null): array
```
- **Zweck:** Items für alle RMAs extrahieren
- **Return:** Array von RMAItems Collections

```php
public function getRMAItem(RMADomainObject $rma, int $shippingNotePosID): RMAItemDomainObject
```
- **Zweck:** Einzelnes Item aus Retoure holen
- **Parameter:** `$shippingNotePosID` - Eindeutiger Identifier
- **Verwendung:** Direkter Zugriff in Smarty-Templates

```php
public function groupRMAItems(RMAItems $rmaItems, string $by = 'order'): RMAItems
```
- **Zweck:** Items gruppieren nach Kriterium
- **Erlaubte Keys:**
  - `'order'` → `orderNo`
  - `'product'` → `productID`
  - `'reason'` → `reasonID`
  - `'status'` → `status`
  - `'day'` → `createDate` (formatiert: d.m.Y)
- **Return:** RMAItems mit neuen Array-Keys
- **Use Case:** Übersichts-Darstellungen

#### Bestellungen

```php
public function getOrderArray(RMAItems $rmaItems): array
```
- **Zweck:** Einzigartige Bestellungen aus Items extrahieren
- **Return:** `[orderID => ['orderNo' => '...', 'orderDate' => '...']]`

```php
public function getOrderNumbers(array $orderIDs): array
```
- **Zweck:** Bestellnummern zu IDs holen
- **Return:** `[orderID => orderNo]`

#### Status-Management

```php
public function getStatusTextByID(int $statusID): string
```
- **Zweck:** Status-ID → Lokalisierter Text
- **Mapping:**
  ```php
  RETURN_STATUS_OPEN        => 'statusOpen'
  RETURN_STATUS_IN_PROGRESS => 'statusProcessing'
  RETURN_STATUS_ACCEPTED    => 'statusAccepted'
  RETURN_STATUS_COMPLETED   => 'statusCompleted'
  RETURN_STATUS_REJECTED    => 'statusRejected'
  ```
- **Error Handling:** Logging bei unbekanntem Status

```php
public function getStatus(RMADomainObject $rma): stdClass
```
- **Zweck:** Status-Objekt für UI erstellen
- **Return:**
  ```php
  {
    text: "Akzeptiert",
    class: "info" // Bootstrap-Class
  }
  ```
- **CSS-Klassen:**
  - `ACCEPTED/IN_PROGRESS` → `info`
  - `COMPLETED` → `success`
  - `REJECTED` → `danger`
  - `OPEN` → `warning`

#### Persistence

```php
public function insertRMA(RMADomainObject $rma): int
```
- **Zweck:** Retoure + Items + Adresse in DB speichern
- **Transaktions-Flow:**
  1. Falls `$rma->id > 0` → `updateRMA()` statt Insert
  2. Insert RMA → Get ID
  3. Insert alle Items (mit `rmaID`)
  4. Insert ReturnAddress (mit `rmaID`)
- **Error Handling:** Logging bei Item/Address-Insert-Fehler
- **Return:** RMA-ID oder 0 bei Fehler
- **⚠️ Hinweis:** Keine echte DB-Transaktion → Inkonsistenzen möglich

```php
public function updateRMA(RMADomainObject $rma): bool
```
- **Zweck:** Existierende Retoure aktualisieren
- **Update-Flow:**
  1. Update alle Items
  2. Update ReturnAddress
  3. Update RMA
- **Return:** `true` bei Erfolg

#### Utilities

```php
public function hashCreateDate(RMADomainObject $rma): string
```
- **Zweck:** MD5-Hash von `createDate`
- **Use Case:** Eindeutige IDs für Cache/URLs

```php
public static function localizeDate(?string $date): string
```
- **Zweck:** Datum formatieren (d.m.Y)
- **Input:** String oder Datum mit `.` oder `-`
- **Error Handling:** Return `'00.00.0000'` bei Parse-Fehler

### Wichtige Erkenntnisse

✅ **Fluent Interface** (`return $this`)  
✅ **Caching** für `rmas` und `returnableProducts`  
✅ **Dependency Injection** für Repositories  
✅ **Logging** via LoggerTrait  
⚠️ **Keine Transaktionen** bei `insertRMA()` → Risiko  
⚠️ **Default-Parameter** aus Session/Config → Testbarkeit eingeschränkt  
🔄 **TODO im Code:** Caching-Funktionalität erweitern

---

## 2. RMAHistoryService

**Zweck:** Event-Sourcing für Retouren-Änderungen

### Dependencies

```php
__construct(public RMAHistoryRepository $RMAHistoryRepository = new RMAHistoryRepository())
```

### Kern-Methoden

#### Event-Detection

```php
public function detectEvents(RMADomainObject $originalDO, RMADomainObject $modifiedDO): array
```
- **Zweck:** Änderungen zwischen zwei RMA-Objekten erkennen
- **Logic:**
  1. `toArray(true)` für beide Objekte
  2. Vergleich aller Properties
  3. Mapping zu Event-Namen via `RMAHistoryEventData::mapEventName()`
- **Type-Safety:** `Typifier::typeify()` für korrekten Vergleich
- **Return:** Array von `RMAHistoryEventData`
- **Ignoriert:** `null`-Werte und keine Änderungen

#### Event-Dispatching

```php
public function dispatchEvents(RMADomainObject $originalDO, RMADomainObject $modifiedDO, array $events = null): void
```
- **Zweck:** Events speichern
- **Logic:** `match()` auf `$eventData->eventName`
- **Unterstützte Events:**
  - `ITEM_MODIFIED` → `itemModified()`
  - `STATUS_CHANGED` → `statusChanged()`
  - `ADDRESS_MODIFIED` → `addressModified()`
  - `REFUND_SHIPPING` → `refundShipping()`
  - `VOUCHER_CREDIT` → `voucherCredit()`
  - `REPLACEMENT_ORDER` → `replacementOrderAssigned()`

#### Private Event-Handler

```php
private function itemModified(RMAHistoryEventData $eventData): void
```
- **Komplexeste Logik:** Erkennt Added/Removed/Quantity-Changed/Reason-Changed
- **Sub-Events:**
  - `ITEM_ADDED` - Neues Item
  - `ITEM_REMOVED` - Item gelöscht
  - `ITEM_MODIFIED_QUANTITY` - Menge geändert
  - `ITEM_MODIFIED_REASON` - Grund geändert
- **Data Structure:**
  ```php
  new RMAEventDataDomainObject(
      shippingNotePosID: ...,
      productID: ...,
      dataBefore: ['quantity' => 5],
      dataAfter: ['quantity' => 3]
  )
  ```

```php
private function statusChanged(RMAHistoryEventData $eventData): void
```
- **Speichert:** `status` before/after

```php
private function addressModified(RMAHistoryEventData $eventData): void
```
- **Logic:**
  - `array_diff()` zwischen Original/Modified
  - Ignoriert `id` und `rmaID`
  - Speichert komplettes Address-Array

```php
private function refundShipping(RMAHistoryEventData $eventData): void
```
- **Speichert:** `refundShipping` Boolean-Änderung

```php
private function voucherCredit(RMAHistoryEventData $eventData): void
```
- **Speichert:** `voucherCredit` Boolean-Änderung

```php
private function replacementOrderAssigned(RMAHistoryEventData $eventData): void
```
- **Speichert:** `replacementOrderID` Änderung

#### Historie abrufen

```php
public function getHistory(RMADomainObject $rma): array
```
- **Zweck:** Alle History-Einträge für RMA laden
- **Logic:**
  1. Load from Repository (`rmaID` Filter)
  2. JSON-Decode `eventDataJson`
  3. Create `RMAEventDataDomainObject` from array
- **Return:** Array von `RMAHistoryDomainObject`

#### Lokalisierung

```php
public function getLocalizedEventDataAsObject(RMAHistoryDomainObject $RMAHistoryDomainObject): object
```
- **Zweck:** History-Event für Frontend-Anzeige aufbereiten
- **Return:**
  ```php
  {
    eventName: "Status geändert",
    localizedText: "Status von 'Offen' zu 'Akzeptiert' geändert",
    dateObject: {
      day: "17",
      month: "Okt",
      year: "2025"
    }
  }
  ```
- **Komplexe Logic:**
  - Produktnamen aus DB laden
  - Retourengründe lokalisieren (via `RMAReasonService`)
  - Status-Texte lokalisieren (via `RMAService`)
  - Adresse formatieren (HTML)

```php
private function convertAddressToString(array $addressArray): string
```
- **Zweck:** Adress-Array → HTML-formatierter String
- **Format:**
  ```
  Firma GmbH<br>
  Herr Dr. Max Mustermann<br>
  Musterstr., 123<br>
  12345 Musterstadt<br>
  Bayern, DE<br>
  0123456789
  ```

```php
private function getLocalizedEventText(...): void
```
- **Zweck:** Event-Namen und Text generieren via `match()`
- **Verwendet:** `sprintf()` mit Language-Keys
- **Komplexität:** Muss Produkte, Gründe, Status auflösen

### Wichtige Erkenntnisse

✅ **Event-Sourcing Pattern**  
✅ **Granulare Item-Events** (Added/Removed/Quantity/Reason)  
✅ **JSON-Storage** für flexible Event-Daten  
✅ **Audit-Trail** - vollständige Nachvollziehbarkeit  
✅ **Lokalisierung** - Frontend-ready  
⚠️ **Komplexe Lokalisierung** - viele Dependencies  
🔄 **Erweiterbar** - Neue Events via `match()` hinzufügen

---

## 3. RMAReturnAddressService

**Zweck:** Verwaltung von Rücksendeadressen

### Dependencies

```php
__construct(public RMAReturnAddressRepository $RMAReturnAddressRepository = new RMAReturnAddressRepository())
```

### Methoden

```php
public function getReturnAddress(RMADomainObject $rma): ?RMAReturnAddressDomainObject
```
- **Zweck:** Rücksendeadresse für RMA laden
- **Caching-Logic:**
  1. Prüft `$rma->getReturnAddress()` (von Constructor)
  2. Falls `null` und `$rma->id > 0` → DB-Query
  3. Typifier für Type-Safety bei DB-Mapping
- **Return:** `null` wenn RMA noch nicht gespeichert

```php
public function returnAddressFromDeliveryAddressTemplateID(int $deliveryAddressTemplateID): RMAReturnAddressDomainObject
```
- **Zweck:** Rücksendeadresse aus Lieferadresse erstellen
- **Use Case:** Kunde wählt existierende Adresse
- **Logic:**
  - Repository-Call: `createFromDeliveryAddressTemplate()`
  - Mapping zu RMAReturnAddressDomainObject
  - Typifier für alle Felder
- **Return:** Leeres DomainObject bei Fehler

```php
public function insertReturnAddress(RMAReturnAddressDomainObject $rmaReturnAddress): int
```
- **Zweck:** Neue Adresse speichern
- **Return:** Insert-ID

```php
public function updateReturnAddress(RMAReturnAddressDomainObject $returnAddress): void
```
- **Zweck:** Existierende Adresse aktualisieren
- **Hinweis:** Kein Return-Wert (void)

### Wichtige Erkenntnisse

✅ **Einfacher Service** - klare Single Responsibility  
✅ **Typifier-Usage** - Type-Safe DB-Mapping  
✅ **Integration** mit Lieferadressen  
⚠️ **Typo im Code:** `firstname` vs. `firstName` (DB vs. Property)

---

## 4. RMAReasonService

**Zweck:** Verwaltung von Retourengründen (mehrsprachig)

### Dependencies

```php
__construct(
    public RMAReasonRepository $RMAReasonRepository = new RMAReasonRepository(),
    public RMAReasonLangRepository $RMAReasonLangRepository = new RMAReasonLangRepository(),
)
```

### Properties

| Property | Typ | Zweck |
|----------|-----|-------|
| `$reasons` | RMAReasonLangDomainObject[] | Cache für geladene Gründe (Key: reasonID) |

### Methoden

```php
public function loadReasons(int $langID): self
```
- **Zweck:** Alle Gründe für Sprache laden
- **Speichert in:** `$this->reasons` (Array mit `reasonID` als Key)
- **Return:** `$this` (Fluent Interface)

```php
public function getReason(int $id, int $languageID): RMAReasonLangDomainObject
```
- **Zweck:** Einzelnen Grund abrufen (mit Lazy-Loading)
- **Logic:**
  1. Prüft `$this->reasons`
  2. Falls leer → `loadReasons()`
  3. Return Grund oder leeres DomainObject

```php
public function saveReason(RMAReasonDomainObject $reason, array $reasonsLocalized): void
```
- **Zweck:** Grund + Lokalisierungen speichern/updaten
- **Logic:**
  - Falls `$reason->id > 0`:
    - Update Grund
    - Update alle Lokalisierungen (falls `id > 0`)
  - Sonst:
    - Insert Grund → Get ID
    - Insert alle Lokalisierungen mit neuer `reasonID`
- **Use Case:** Admin-Bereich (Grund-Verwaltung)

### Wichtige Erkenntnisse

✅ **Lazy-Loading** für Gründe  
✅ **Multi-Language** via separate Localization-Table  
✅ **Fluent Interface**  
⚠️ **Admin-fokussiert** - `saveReason()` nicht für Frontend  

---

## Service-Dependencies Diagramm

```
RMAService
├── RMARepository
├── RMAItemRepository
└── RMAReturnAddressService
    └── RMAReturnAddressRepository

RMAHistoryService
├── RMAHistoryRepository
├── RMAService (für Status-Texte)
└── RMAReasonService (für Grund-Texte)

RMAReturnAddressService
└── RMAReturnAddressRepository

RMAReasonService
├── RMAReasonRepository
└── RMAReasonLangRepository
```

---

## Wiederverwendbarkeit für Customer Returns Plugin

### ✅ Direkt nutzbar

- **RMAService**
  - `getReturnableProducts()` - Produktauswahl
  - `insertRMA()` - Retoure erstellen
  - `getStatus()` - Status-Anzeige
  - `isOrderReturnable()` - Validierung

- **RMAHistoryService**
  - `detectEvents()` + `dispatchEvents()` - Auto-Logging
  - `getHistory()` - Historie-Anzeige

- **RMAReturnAddressService**
  - `returnAddressFromDeliveryAddressTemplateID()` - Adress-Auswahl
  - `insertReturnAddress()` - Adresse speichern

- **RMAReasonService**
  - `getReason()` - Gründe anzeigen
  - `loadReasons()` - Dropdown-Daten

### 🔄 Anpassungen für Customer Returns

Keine Breaking Changes nötig! Alle Services sind **vollständig kompatibel**.

**Mögliche Erweiterungen:**
```php
class ReturnRequestService extends RMAService {
    public function validateOrderAccess(string $orderNo, string $email): bool
    public function createReturnFromOrder(int $orderID, array $items): RMADomainObject
}
```

---

## Best Practices aus Code

1. **Caching:** `$rmas` und `$returnableProducts` vermeiden redundante DB-Calls
2. **Fluent Interface:** `loadReturns()->getRMAItems()`
3. **Error Handling:** Logging statt Exceptions (UX-freundlich)
4. **Type-Safety:** Typifier für DB-Mapping
5. **Event-Sourcing:** Änderungen tracken statt Snapshots
6. **Lazy-Loading:** `getReason()` lädt nur bei Bedarf

---

## Nächste Schritte

1. ✅ **RMAService** - Verstanden
2. ✅ **RMAHistoryService** - Verstanden
3. ✅ **RMAReturnAddressService** - Verstanden
4. ✅ **RMAReasonService** - Verstanden
5. ⬜ **Repositories analysieren** - SQL-Struktur verstehen
6. ⬜ **Helper-Klassen** - `RMAItems`, `RMAHistoryEvents`, `RMAHistoryEventData`

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** Services sind **vollständig wiederverwendbar** und bieten alle benötigten Features für Customer Returns Plugin
