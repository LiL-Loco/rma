# RMA Repositories - Code-Analyse & SQL-Struktur

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** `jtl_rma_beispiel/RMA/Repositories/`

---

## Übersicht

Die Repositories implementieren das **Repository Pattern** und bilden die **Data Access Layer**. Alle erben von `AbstractDBRepository` und nutzen das JTL-interne `DbInterface` für Datenbankzugriffe.

### Verfügbare Repositories

1. **RMARepository** - Haupt-Repository (komplex mit JOINs)
2. **RMAItemRepository** - Items (einfache CRUD)
3. **RMAReasonRepository** - Retourengründe (einfache CRUD)
4. **RMAReasonLangRepository** - Lokalisierte Gründe (einfache CRUD)
5. **RMAReturnAddressRepository** - Rücksendeadressen + Helper
6. **RMAHistoryRepository** - Historie + Helper

---

## 1. RMARepository (Hauptrepository)

**Zweck:** Komplexe Queries für Retouren mit JOINs über mehrere Tabellen

### Basis-Methoden (von AbstractDBRepository)

```php
public function getTableName(): string
```
- **Return:** `'rma'`

### SQL-Struktur-Analyse

#### Hauptquery: `getReturns()`

**Involvierte Tabellen:**
```
rma (Haupttabelle)
├── rma_items (RIGHT JOIN)
├── tlieferscheinpos (LEFT JOIN) - Lieferschein-Positionen
├── rma_reasons (LEFT JOIN)
├── rma_reasons_lang (LEFT JOIN)
├── return_address (LEFT JOIN)
├── tbestellung (LEFT JOIN) - Bestellungen
├── twarenkorbpos (LEFT JOIN) - Warenkorb-Positionen
├── twarenkorbposeigenschaft (LEFT JOIN) - Eigenschaften/Varianten
├── teigenschaftsprache (LEFT JOIN) - Lokalisierte Eigenschaftsnamen
├── teigenschaftwertsprache (LEFT JOIN) - Lokalisierte Werte
├── tseo (LEFT JOIN) - SEO-URLs
└── tartikel (LEFT JOIN) - Produktdaten
```

**Ausgewählte Spalten (Aliase):**
```sql
-- RMA-Daten (Prefix: rma_)
rma.id AS rma_id
rma.wawiID
rma.customerID
rma.replacementOrderID
rma.rmaNr
rma.voucherCredit
rma.refundShipping
rma.synced AS rma_synced
rma.status AS rma_status
rma.comment AS rma_comment
rma.createDate AS rma_createDate
rma.lastModified AS rma_lastModified

-- RMA-Items (Prefix: rma_item_)
items.id AS rma_item_id
items.orderPosID
items.orderID
items.productID
items.reasonID
items.name
items.variationProductID
items.variationName
items.variationValue
items.partListProductID
items.partListProductName
items.partListProductURL
items.partListProductNo
items.unitPriceNet
items.quantity
items.vat
items.unit
items.shippingNotePosID
items.comment AS rma_item_comment
items.status AS rma_item_status
items.createDate AS rma_item_createDate

-- Rücksendeadresse (Prefix: return_address_)
return_address.id AS return_address_id
return_address.salutation AS return_address_salutation
return_address.firstName AS return_address_firstName
... (alle Adressfelder)

-- Retourengründe (Prefix: rma_reasons_)
rma_reasons.id AS rma_reasons_id
rma_reasons.wawiID AS rma_reasons_wawiID
rma_reasons_lang.id AS rma_reasons_lang_id
rma_reasons_lang.reasonID
rma_reasons_lang.langID
rma_reasons_lang.title AS rma_reasons_lang_title

-- Zusatzdaten
shippingNote.kLieferschein AS shippingNoteID
tbestellung.cBestellNr AS orderNo
tbestellung.dErstellt AS orderDate
tseo.cSeo AS seo
tartikel.cArtNr AS productNo
tartikel.cTeilbar AS isDivisible
```

**Filter-Optionen:**
```php
$filter = [
    'customerID' => 123,     // AND rma.customerID = :customerID
    'id' => 456,             // AND rma.id = :id
    'status' => 1,           // AND rma.status = :status
    'beforeDate' => '2025-01-01', // AND rma.createDate < :beforeDate
    'product' => 789,        // AND items.productID = :pID
    'shippingNote' => 101,   // AND shippingNote.kLieferschein = :sID
    'synced' => 1            // AND rma.synced = :synced
];
```

**GROUP BY & ORDER BY:**
```sql
GROUP BY items.id
ORDER BY rma.id DESC
```

#### Query: `getReturnableProducts()`

**Zweck:** Produkte ermitteln, die retourniert werden können

**Business Rules (im SQL):**
1. **Retourenfrist:** `DATE(FROM_UNIXTIME(tversand.dErstellt)) >= DATE_SUB(NOW(), INTERVAL :cancellationTime DAY)`
2. **Bestellstatus:** `tbestellung.cStatus IN (:status_versandt, :status_teilversandt)`
3. **Nicht retournierbar:** `tartikelattribut.cWert IS NULL` (Attribut `FKT_ATTRIBUT_PRODUCT_NOT_RETURNABLE`)
4. **Keine Downloads:** `tartikeldownload.kArtikel IS NULL`
5. **Bereits retourniert:** `tlieferscheinpos.fAnzahl - SUM(IFNULL(rma_items.quantity, 0)) AS quantity`

**Involvierte Tabellen:**
```
tbestellung
├── twarenkorbpos (INNER JOIN) - Nur Artikel (kArtikel > 0)
├── tlieferscheinpos (INNER JOIN) - Lieferschein-Positionen
├── tversand (LEFT JOIN) - Versanddaten (für Retourenfrist)
├── tartikelattribut (LEFT JOIN) - Nicht-retournierbar-Attribut
├── tartikeldownload (LEFT JOIN) - Download-Produkte ausschließen
├── twarenkorbposeigenschaft (LEFT JOIN) - Varianten
├── teigenschaftsprache (LEFT JOIN) - Lokalisierte Eigenschaften
├── teigenschaftwertsprache (LEFT JOIN) - Lokalisierte Werte
├── tartikel (INNER JOIN) - Produktdaten
├── tstueckliste (LEFT JOIN) - Stücklisten
└── rma_items (LEFT JOIN) - Bereits retourniert
```

**Berechnete Felder:**
```sql
(tlieferscheinpos.fAnzahl - SUM(IFNULL(rma_items.quantity, 0))) AS quantity
DATE_FORMAT(FROM_UNIXTIME(tversand.dErstellt), '%d-%m-%Y') AS createDate
GROUP_CONCAT(tstueckliste.kArtikel SEPARATOR ',') AS partListProductIDs
```

**GROUP BY:**
```sql
GROUP BY tlieferscheinpos.kLieferscheinPos
```

### Wichtige Methoden

#### `getReturns()`
```php
public function getReturns(int $langID, array $filter = [], ?int $limit = null): array
```
- **Rückgabe:** `RMADomainObject[]` (Key: RMA-ID)
- **Logik:**
  1. SQL-Query mit Filtern
  2. `getReturnPositions()` - DB-Aufruf + Type-Casting
  3. `getReturnsAsArray()` - Gruppierung nach RMA-ID
  4. Erstelle `RMADomainObject` mit `RMAItems` Collection

#### `getReturnableProducts()`
```php
public function getReturnableProducts(int $customerID, int $languageID, int $cancellationTime, int $orderID = 0): array
```
- **Rückgabe:** `RMAItemDomainObject[]`
- **Spezial-Logik:**
  - Filtert `quantity <= 0`
  - Handhabt **Stücklisten** via `handlePartlistProducts()`
  - Berechnet retournierbare Menge pro Stücklistenartikel

#### `getOrderNumbers()`
```php
public function getOrderNumbers(array $orderIDs): array
```
- **Rückgabe:** `[orderID => orderNo]`
- **Simple Query:** `SELECT kBestellung, cBestellNr FROM tbestellung WHERE kBestellung IN (...)`

#### `markedAsSynced()`
```php
public function markedAsSynced(array $rmaDomainObjects): bool
```
- **Zweck:** Bulk-Update `synced = 1` für mehrere RMAs
- **SQL:** `UPDATE rma SET synced = 1 WHERE id IN (...)`
- **Return:** `true` wenn mindestens 1 Row affected

### Private Helper-Methoden

#### `initArtikel()`
```php
private function initArtikel(int $productID, array $data): Artikel
```
- Erstellt `Artikel`-Objekt (JTL Core-Klasse)
- Ruft `$product->holBilder()` auf (Bilder laden)

#### `initRMAItem()`
```php
private function initRMAItem(object $product): RMAItemDomainObject
```
- Mapped stdClass → `RMAItemDomainObject`
- 20+ Properties mit Null-Coalescence

#### `initReturnAddress()`
```php
private function initReturnAddress(array $addressData): RMAReturnAddressDomainObject
```
- Mapped Array → `RMAReturnAddressDomainObject`
- Alle Adressfelder mit Prefix `return_address_`

#### `initReason()`
```php
private function initReason(int $langID, array $data): ?RMAReasonLangDomainObject
```
- Mapped Array → `RMAReasonLangDomainObject`

#### Stücklisten-Handling

```php
private function handlePartlistProducts(string $partListProductIDs, int $languageID, object $product, array &$products): void
```
- **Logik:**
  1. `getPartListProducts()` - Hole alle Stücklistenartikel
  2. Berechne retournierbare Menge: `(partListQuantity * orderQuantity) - returnedQuantity`
  3. Filtere `<= 0`
  4. `mergePartListProductData()` - Kombiniere Haupt + Stücklisten-Daten

### Wichtige Erkenntnisse

✅ **Sehr komplexe Queries** - 12+ JOINs  
✅ **Business Logic im SQL** - Retourenfrist, Status-Filter, Mengenberechnung  
✅ **Stücklisten-Support** - Retourniere Einzelteile statt Hauptprodukt  
✅ **Multi-Language** - `langID` für Lokalisierung  
✅ **Variant-Support** - `twarenkorbposeigenschaft` JOIN  
⚠️ **Performance-Risiko** - Große JOINs ohne Limit  
⚠️ **N+1 bei Artikel-Bildern** - `holBilder()` in Schleife  
🔄 **Type-Safety** - `Typifier::intifyOrNull()` für nullable Integers

---

## 2. RMAItemRepository

**Zweck:** CRUD für `rma_items` Tabelle

### Methoden

```php
public function getTableName(): string
```
- **Return:** `'rma_items'`

**Hinweis:** Nutzt nur CRUD-Methoden von `AbstractDBRepository`:
- `insert(RMAItemDomainObject $item): int`
- `update(RMAItemDomainObject $item): bool`
- `delete(int $id): bool`
- `filter(array $filter): ?object`
- `getList(array $filter): array`

### Wichtige Erkenntnisse

✅ **Minimalistisch** - Keine Custom-Methoden  
✅ **AbstractDBRepository deckt alle Needs ab**  
✅ **Type-Safe** - Domain Objects als Parameter  

---

## 3. RMAReasonRepository

**Zweck:** CRUD für `rma_reasons` Tabelle

### Methoden

```php
public function getTableName(): string
```
- **Return:** `'rma_reasons'`

**Gleiche CRUD-Methoden wie RMAItemRepository**

---

## 4. RMAReasonLangRepository

**Zweck:** CRUD für `rma_reasons_lang` Tabelle (Lokalisierung)

### Methoden

```php
public function getTableName(): string
```
- **Return:** `'rma_reasons_lang'`

**Gleiche CRUD-Methoden wie RMAItemRepository**

---

## 5. RMAReturnAddressRepository

**Zweck:** CRUD + Adress-Mapping aus Lieferadressen

### Methoden

```php
public function getTableName(): string
```
- **Return:** `'return_address'`

```php
public function generateID(): int
```
- **Zweck:** Nächste freie ID generieren
- **SQL:** `SELECT MAX(id) as id FROM return_address`
- **Return:** `MAX(id) + 1`
- **⚠️ Race Condition:** Kein Auto-Increment, manuelles ID-Management

```php
public function createFromDeliveryAddressTemplate(int $deliveryAddressTemplateID): ?array
```
- **Zweck:** Lieferadresse → Rücksendeadresse konvertieren
- **Logik:**
  1. Lade `DeliveryAddressTemplate` (JTL Core-Klasse)
  2. Falls `kKunde === null` → Return null
  3. Bundesland-ISO → Name via `Staat::getRegionByIso()`
  4. Mapped alle Felder zu Array
- **Return:** Array mit allen Adressfeldern + generierte ID
- **Use Case:** Kunde wählt existierende Lieferadresse

### Wichtige Erkenntnisse

✅ **Integration** mit JTL Core (`DeliveryAddressTemplate`, `Staat`)  
✅ **Bundesland-Konvertierung** - ISO-Code → Name  
⚠️ **Manuelle ID-Generierung** - Potential Race Condition  
🔄 **Helper-Funktion** für Service-Layer  

---

## 6. RMAHistoryRepository

**Zweck:** CRUD + Produktname-Helper

### Methoden

```php
public function getTableName(): string
```
- **Return:** `'rma_history'`

```php
public function getProductNameFromDB(int $productID, int $shippingNotePosID): ?object
```
- **Zweck:** Produktname für History-Lokalisierung laden
- **SQL:**
  ```sql
  SELECT name FROM rma_items 
  WHERE productID = :productID 
    AND shippingNotePosID = :shippingNotePosID
  ```
- **Return:** `object{name: string}` oder `null`
- **Use Case:** `RMAHistoryService->getLocalizedEventDataAsObject()`

### Wichtige Erkenntnisse

✅ **Spezial-Query** für History-Service  
✅ **Simple SELECT** - Keine JOINs  
✅ **Returns stdClass** - Flexibel für Template-Engine  

---

## Datenbank-Schema (abgeleitet aus SQL)

### Tabelle: `rma`

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT | ❌ | AUTO_INCREMENT | Primary Key |
| `wawiID` | INT | ✅ | NULL | Wawi-Referenz |
| `customerID` | INT | ❌ | - | FK zu Kunde |
| `replacementOrderID` | INT | ✅ | NULL | FK zu Ersatzbestellung |
| `rmaNr` | VARCHAR | ✅ | NULL | Retourennummer |
| `voucherCredit` | BOOLEAN/TINYINT | ❌ | 0 | Gutschrift? |
| `refundShipping` | BOOLEAN/TINYINT | ❌ | 0 | Versand erstatten? |
| `synced` | BOOLEAN/TINYINT | ❌ | 0 | Mit Wawi synchr.? |
| `status` | INT | ❌ | 1 | Status-ID |
| `comment` | TEXT | ✅ | NULL | Kundenkommentar |
| `createDate` | DATETIME | ❌ | CURRENT_TIMESTAMP | Erstellt am |
| `lastModified` | DATETIME | ✅ | NULL | Geändert am |

**Indizes:**
- PRIMARY KEY (`id`)
- INDEX (`customerID`)
- INDEX (`status`)
- INDEX (`synced`)

---

### Tabelle: `rma_items`

| Spalte | Typ | Nullable | Beschreibung |
|--------|-----|----------|--------------|
| `id` | INT | ❌ | Primary Key |
| `rmaID` | INT | ❌ | FK zu `rma.id` |
| `shippingNotePosID` | INT | ✅ | FK zu `tlieferscheinpos` |
| `orderID` | INT | ✅ | FK zu `tbestellung` |
| `orderPosID` | INT | ✅ | FK zu `twarenkorbpos` |
| `productID` | INT | ✅ | FK zu `tartikel` |
| `reasonID` | INT | ✅ | FK zu `rma_reasons` |
| `name` | VARCHAR(255) | ❌ | Produktname |
| `variationProductID` | INT | ✅ | Varianten-Produkt-ID |
| `variationName` | VARCHAR | ✅ | Variantenname |
| `variationValue` | VARCHAR | ✅ | Variantenwert |
| `partListProductID` | INT | ✅ | Stücklisten-Haupt-Artikel |
| `partListProductName` | VARCHAR | ✅ | Stücklistenname |
| `partListProductURL` | VARCHAR | ✅ | Stücklisten-URL |
| `partListProductNo` | VARCHAR | ✅ | Stücklisten-Artikelnr. |
| `unitPriceNet` | DECIMAL(10,2) | ❌ | Netto-Einzelpreis |
| `quantity` | DECIMAL(10,2) | ❌ | Menge |
| `vat` | DECIMAL(5,2) | ❌ | MwSt.-Satz |
| `unit` | VARCHAR | ✅ | Mengeneinheit |
| `comment` | TEXT | ✅ | Kommentar zur Position |
| `status` | VARCHAR | ✅ | Item-Status |
| `createDate` | DATETIME | ❌ | Erstellt am |

**Indizes:**
- PRIMARY KEY (`id`)
- INDEX (`rmaID`)
- INDEX (`shippingNotePosID`)
- INDEX (`productID`)

---

### Tabelle: `rma_reasons`

| Spalte | Typ | Nullable | Beschreibung |
|--------|-----|----------|--------------|
| `id` | INT | ❌ | Primary Key |
| `wawiID` | INT | ❌ | Wawi-Referenz |
| `productTypeGroupID` | INT | ✅ | Produkttyp-Gruppe |

**Indizes:**
- PRIMARY KEY (`id`)
- UNIQUE (`wawiID`)

---

### Tabelle: `rma_reasons_lang`

| Spalte | Typ | Nullable | Beschreibung |
|--------|-----|----------|--------------|
| `id` | INT | ❌ | Primary Key |
| `reasonID` | INT | ❌ | FK zu `rma_reasons.id` |
| `langID` | INT | ❌ | FK zu `tsprache` |
| `title` | VARCHAR(255) | ❌ | Lokalisierter Text |

**Indizes:**
- PRIMARY KEY (`id`)
- UNIQUE (`reasonID`, `langID`)

---

### Tabelle: `return_address`

| Spalte | Typ | Nullable | Beschreibung |
|--------|-----|----------|--------------|
| `id` | INT | ❌ | Primary Key (manuell!) |
| `rmaID` | INT | ❌ | FK zu `rma.id` |
| `customerID` | INT | ❌ | FK zu Kunde |
| `salutation` | VARCHAR(10) | ❌ | Anrede (m/w/d) |
| `firstName` | VARCHAR(100) | ❌ | Vorname |
| `lastName` | VARCHAR(100) | ❌ | Nachname |
| `academicTitle` | VARCHAR(50) | ✅ | Titel |
| `companyName` | VARCHAR(255) | ✅ | Firma |
| `companyAdditional` | VARCHAR(255) | ✅ | Firmenzusatz |
| `street` | VARCHAR(255) | ❌ | Straße |
| `houseNumber` | VARCHAR(20) | ❌ | Hausnummer |
| `addressAdditional` | VARCHAR(255) | ✅ | Adresszusatz |
| `postalCode` | VARCHAR(20) | ❌ | PLZ |
| `city` | VARCHAR(100) | ❌ | Stadt |
| `state` | VARCHAR(100) | ❌ | Bundesland |
| `countryISO` | VARCHAR(2) | ❌ | Ländercode |
| `phone` | VARCHAR(50) | ✅ | Telefon |
| `mobilePhone` | VARCHAR(50) | ✅ | Mobil |
| `fax` | VARCHAR(50) | ✅ | Fax |
| `mail` | VARCHAR(255) | ✅ | E-Mail |

**Indizes:**
- PRIMARY KEY (`id`)
- UNIQUE (`rmaID`)
- INDEX (`customerID`)

---

### Tabelle: `rma_history`

| Spalte | Typ | Nullable | Beschreibung |
|--------|-----|----------|--------------|
| `id` | INT | ❌ | Primary Key |
| `rmaID` | INT | ❌ | FK zu `rma.id` |
| `eventName` | VARCHAR(100) | ❌ | Event-Type |
| `eventDataJson` | TEXT | ❌ | JSON-Daten |
| `createDate` | DATETIME | ❌ | Zeitstempel |

**Indizes:**
- PRIMARY KEY (`id`)
- INDEX (`rmaID`)
- INDEX (`createDate`)

---

## ER-Diagramm

```
┌─────────────┐
│   rma       │
├─────────────┤
│ id (PK)     │◄─────────┬──────────────┐
│ wawiID      │          │              │
│ customerID  │          │              │
│ status      │          │              │
│ ...         │          │              │
└─────────────┘          │              │
                         │              │
┌─────────────┐          │              │
│ rma_items   │          │              │
├─────────────┤          │              │
│ id (PK)     │          │              │
│ rmaID (FK)  ├──────────┘              │
│ productID   ├──┐                      │
│ reasonID    ├─┐│                      │
│ ...         │ ││                      │
└─────────────┘ ││                      │
                ││                      │
┌─────────────┐ ││  ┌─────────────────┐│
│ rma_reasons │◄┘│  │ return_address  ││
├─────────────┤  │  ├─────────────────┤│
│ id (PK)     │  │  │ id (PK)         ││
│ wawiID      │  │  │ rmaID (FK)      ├┘
└─────────────┘  │  │ customerID      │
       ▲         │  │ ...             │
       │         │  └─────────────────┘
┌─────────────┐  │
│ rma_reasons │  │  ┌─────────────────┐
│    _lang    │  │  │  rma_history    │
├─────────────┤  │  ├─────────────────┤
│ id (PK)     │  │  │ id (PK)         │
│ reasonID(FK)├──┘  │ rmaID (FK)      ├──┐
│ langID      │     │ eventDataJson   │  │
│ title       │     │ ...             │  │
└─────────────┘     └─────────────────┘  │
                            ▲             │
                            └─────────────┘

┌─────────────┐
│ tartikel    │
├─────────────┤
│ kArtikel(PK)│◄──── productID
│ cArtNr      │
│ ...         │
└─────────────┘

┌─────────────┐
│ tbestellung │
├─────────────┤
│kBestellung  │◄──── orderID
│ cBestellNr  │
│ kKunde      │
│ ...         │
└─────────────┘
```

---

## Wiederverwendbarkeit für Customer Returns Plugin

### ✅ Vollständig nutzbar

- **RMARepository**
  - `getReturnableProducts()` - Perfekt für Produktauswahl-UI
  - `getReturns()` - Listen-Ansicht
  - `markedAsSynced()` - Nach Wawi-Sync

- **RMAItemRepository** - Standard CRUD

- **RMAReasonRepository** + **RMAReasonLangRepository** - Grund-Verwaltung

- **RMAReturnAddressRepository**
  - `createFromDeliveryAddressTemplate()` - Adress-Auswahl

- **RMAHistoryRepository**
  - `getProductNameFromDB()` - History-Anzeige

### 🔧 Zu beachten

- **return_address.id** - Manuelle ID-Generierung (Race Condition möglich)
- **Performance** - `getReturnableProducts()` ohne Limit kann langsam werden
- **Artikel-Bilder** - `holBilder()` in Loop → N+1 Problem

### 🔄 Mögliche Optimierungen

```php
// In ReturnRequestService (Extended Repository)
public function getReturnableProductsOptimized(int $orderID): array {
    $products = $this->RMARepository->getReturnableProducts(...);
    
    // Batch-Load Bilder
    $productIDs = array_map(fn($p) => $p->productID, $products);
    $images = $this->loadProductImagesBatch($productIDs);
    
    foreach ($products as &$product) {
        $product->getProduct()->oImage = $images[$product->productID];
    }
    
    return $products;
}
```

---

## Nächste Schritte

1. ✅ **RMARepository** - SQL verstanden
2. ✅ **Einfache Repositories** - CRUD-Pattern
3. ⬜ **Sync-Objekte analysieren** - dbeS-Integration
4. ⬜ **Helper-Klassen** - `RMAItems`, Events
5. ⬜ **Templates analysieren** - Frontend-Logik

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** Repositories vollständig verstanden. **Datenbank-Schema dokumentiert**. SQL-Queries analysiert. Wiederverwendbar für Customer Returns Plugin.
