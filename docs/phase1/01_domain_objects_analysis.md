# RMA Domain Objects - Code-Analyse

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** `jtl_rma_beispiel/RMA/DomainObjects/`

---

## Übersicht

Die vorhandenen RMA Domain Objects bilden die **Datenstrukturen** für das Retourensystem ab. Alle Objekte erben von `AbstractDomainObject` und nutzen **readonly Properties** (PHP 8.1+) für Immutability.

### Verfügbare Domain Objects

1. **RMADomainObject** - Haupt-Retourenobjekt
2. **RMAItemDomainObject** - Einzelne Retourenposition
3. **RMAReasonDomainObject** - Retourengrund (Stammdaten)
4. **RMAReasonLangDomainObject** - Mehrsprachige Retourengründe
5. **RMAReturnAddressDomainObject** - Rücksendeadresse
6. **RMAHistoryDomainObject** - Änderungshistorie
7. **RMAEventDataDomainObject** - Event-Daten für History

---

## 1. RMADomainObject

**Zweck:** Container für eine vollständige Retourenanfrage (Shop oder Wawi-Import)

### Properties (DB-Spalten)

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `id` | int | ❌ | Primary Key |
| `wawiID` | int | ✅ | Wawi-Referenz-ID |
| `customerID` | int | ❌ | Kunden-ID |
| `replacementOrderID` | int | ✅ | Ersatzbestellung-ID |
| `rmaNr` | string | ✅ | Retourennummer |
| `voucherCredit` | bool | ❌ | Gutschrift statt Rückzahlung |
| `refundShipping` | bool | ❌ | Versandkosten erstatten |
| `synced` | bool | ❌ | Mit Wawi synchronisiert |
| `status` | int | ❌ | Status-ID (Default: 1) |
| `comment` | string | ✅ | Kundenkommentar |
| `createDate` | string | ❌ | Erstellungsdatum (Auto: Y-m-d H:i:s) |
| `lastModified` | string | ✅ | Letzte Änderung |

### Private Properties (Beziehungen)

| Property | Typ | Beschreibung |
|----------|-----|--------------|
| `items` | RMAItems | Collection von RMAItemDomainObject |
| `returnAddress` | RMAReturnAddressDomainObject | Rücksendeadresse |

### Methoden

```php
public function getRMAItems(): RMAItems
```
- **Return:** RMAItems-Collection (oder leere Collection)
- **Verwendung:** Zugriff auf Retourenpositionen

```php
public function getReturnAddress(): ?RMAReturnAddressDomainObject
```
- **Return:** Rücksendeadresse oder null

```php
public static function orderStatusToString(int $status): string
```
- **Parameter:** Status-ID (int)
- **Return:** String-Repräsentation (z.B. 'RETURN_STATUS_OPEN')
- **Status-Konstanten:**
  - `RETURN_STATUS_OPEN` - Offen
  - `RETURN_STATUS_IN_PROGRESS` - In Bearbeitung
  - `RETURN_STATUS_ACCEPTED` - Akzeptiert
  - `RETURN_STATUS_COMPLETED` - Abgeschlossen
  - `RETURN_STATUS_REJECTED` - Abgelehnt

### Wichtige Erkenntnisse

✅ **Wiederverwendbar für Customer Returns Plugin**  
✅ **Status-System bereits implementiert** (5 Stati)  
✅ **Unterstützt Gutschrift vs. Rückzahlung** (`voucherCredit`)  
✅ **Versandkosten-Erstattung konfigurierbar** (`refundShipping`)  
⚠️ **RMAItems ist eine Collection-Klasse** (separat analysieren)

---

## 2. RMAItemDomainObject

**Zweck:** DTO für einzelne Retourenposition oder retournierbares Produkt

### Properties (DB-Spalten)

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `id` | int | ❌ | Primary Key |
| `rmaID` | int | ❌ | Foreign Key zu RMA |
| `shippingNotePosID` | int | ✅ | Lieferschein-Position-ID |
| `orderID` | int | ✅ | Bestellungs-ID |
| `orderPosID` | int | ✅ | Bestellposition-ID |
| `productID` | int | ✅ | Produkt-ID |
| `reasonID` | int | ✅ | Foreign Key zu RMAReasonDomainObject |
| `name` | string | ❌ | Produktname |
| `variationProductID` | int | ✅ | Varianten-Produkt-ID |
| `variationName` | string | ✅ | Varianten-Name |
| `variationValue` | string | ✅ | Varianten-Wert |
| `partListProductID` | int | ✅ | Stücklisten-Produkt-ID |
| `partListProductName` | string | ✅ | Stücklisten-Produktname |
| `partListProductURL` | string | ✅ | Stücklisten-Produkt-URL |
| `partListProductNo` | string | ✅ | Stücklisten-Produktnummer |
| `unitPriceNet` | float | ❌ | Netto-Einzelpreis |
| `quantity` | float | ❌ | Menge |
| `vat` | float | ❌ | MwSt.-Satz |
| `unit` | string | ✅ | Mengeneinheit |
| `comment` | string | ✅ | Kommentar zur Position |
| `status` | string | ✅ | Item-Status |
| `createDate` | string | ❌ | Erstellungsdatum |

### Private Properties (Zusatzdaten)

| Property | Typ | Beschreibung |
|----------|-----|--------------|
| `history` | array | Historie-Einträge |
| `product` | Artikel | JTL Artikel-Objekt |
| `reason` | RMAReasonLangDomainObject | Lokalisierter Retourengrund |
| `productNo` | string | Produktnummer |
| `orderStatus` | string | Bestellstatus |
| `seo` | string | SEO-URL |
| `orderNo` | string | Bestellnummer |
| `orderDate` | string | Bestelldatum |
| `customerID` | int | Kunden-ID |
| `shippingAddressID` | int | Lieferadresse-ID |
| `shippingNoteID` | int | Lieferschein-ID |

### Methoden (Getter)

```php
public function getHistory(): ?array
public function getProduct(): ?Artikel
public function getReason(): ?RMAReasonLangDomainObject
public function getProductNo(): string
public function getOrderStatus(): string
public function getSeo(): string
public function getOrderNo(): string
public function getOrderDate(string $format = 'Y-m-d H:i:s'): string
public function getCustomerID(): int
public function getShippingAddressID(): int
public function getShippingNoteID(): int
```

### Wichtige Erkenntnisse

✅ **Sehr umfangreich** - alle relevanten Daten für Retoure enthalten  
✅ **Unterstützt Varianten** (`variationProductID`, `variationName`, `variationValue`)  
✅ **Stücklisten-Support** (`partListProduct*`)  
✅ **Preis-Tracking** (`unitPriceNet`, `vat`)  
✅ **Beziehung zu Bestellung** (`orderID`, `orderPosID`)  
⚠️ **Viele optionale Felder** - Flexibilität vs. Komplexität

---

## 3. RMAReasonDomainObject

**Zweck:** Stammdaten für Retourengründe

### Properties

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `id` | int | ❌ | Primary Key |
| `wawiID` | int | ❌ | Wawi-Referenz |
| `productTypeGroupID` | int | ✅ | Produkttyp-Gruppen-ID |

### Wichtige Erkenntnisse

⚠️ **Minimales Objekt** - nur IDs, keine Texte  
✅ **Mehrsprachigkeit** über `RMAReasonLangDomainObject`  
✅ **Produkttyp-spezifisch** (optional)  

---

## 4. RMAReasonLangDomainObject

**Zweck:** Lokalisierte Bezeichnungen für Retourengründe

### Properties

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `id` | int | ❌ | Primary Key |
| `reasonID` | int | ❌ | Foreign Key zu RMAReasonDomainObject |
| `langID` | int | ❌ | Sprach-ID |
| `title` | string | ❌ | Lokalisierter Grund-Text |

### Wichtige Erkenntnisse

✅ **Standard JTL Multi-Language-Pattern**  
✅ **Einfach erweiterbar** für neue Sprachen  

---

## 5. RMAReturnAddressDomainObject

**Zweck:** Rücksendeadresse des Kunden

### Properties

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `id` | int | ❌ | Primary Key |
| `rmaID` | int | ❌ | Foreign Key zu RMA |
| `customerID` | int | ❌ | Kunden-ID |
| `salutation` | string | ❌ | Anrede |
| `firstName` | string | ❌ | Vorname |
| `lastName` | string | ❌ | Nachname |
| `academicTitle` | string | ✅ | Akademischer Titel |
| `companyName` | string | ✅ | Firmenname |
| `companyAdditional` | string | ✅ | Firmenzusatz |
| `street` | string | ❌ | Straße |
| `houseNumber` | string | ❌ | Hausnummer |
| `addressAdditional` | string | ✅ | Adresszusatz |
| `postalCode` | string | ❌ | PLZ |
| `city` | string | ❌ | Stadt |
| `state` | string | ❌ | Bundesland/Region |
| `countryISO` | string | ❌ | Ländercode (ISO) |
| `phone` | string | ✅ | Telefon |
| `mobilePhone` | string | ✅ | Mobiltelefon |
| `fax` | string | ✅ | Fax |
| `mail` | string | ✅ | E-Mail |

### Wichtige Erkenntnisse

✅ **Vollständige Adressdaten**  
✅ **Internationale Adressen** (countryISO, state)  
✅ **Business + Privat** (companyName optional)  
✅ **Kontaktdaten** (phone, mobilePhone, fax, mail)  

---

## 6. RMAHistoryDomainObject

**Zweck:** Änderungshistorie für Retouren

### Properties

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `id` | int | ❌ | Primary Key |
| `rmaID` | int | ❌ | Foreign Key zu RMA |
| `eventName` | string | ❌ | Event-Name (z.B. 'status_changed') |
| `eventDataJson` | string | ❌ | JSON-serialisierte Event-Daten |
| `createDate` | string | ❌ | Zeitstempel |

### Private Properties

| Property | Typ | Beschreibung |
|----------|-----|--------------|
| `eventDataDomainObject` | RMAEventDataDomainObject | Deserialisierte Event-Daten |

### Methoden

```php
public function getEventDataDomainObject(): ?RMAEventDataDomainObject
```

### Wichtige Erkenntnisse

✅ **Event-Sourcing-Pattern**  
✅ **JSON-Storage** für flexible Datenstrukturen  
✅ **Audit-Trail** für Compliance  
⚠️ **Deserialisierung nötig** für strukturierten Zugriff  

---

## 7. RMAEventDataDomainObject

**Zweck:** Strukturierte Event-Daten für History

### Properties

| Property | Typ | Nullable | Beschreibung |
|----------|-----|----------|--------------|
| `shippingNotePosID` | int | ❌ | Lieferschein-Position |
| `productID` | int | ❌ | Produkt-ID |
| `dataBefore` | array | ❌ | Daten vor Änderung |
| `dataAfter` | array | ❌ | Daten nach Änderung |

### Wichtige Erkenntnisse

⚠️ **Keine DB-Tabelle** - nur für JSON-Serialisierung  
✅ **Before/After-Pattern** für Change-Tracking  
✅ **Flexible Datenstruktur** (arrays)  

---

## Beziehungs-Diagramm

```
RMADomainObject (1)
├── RMAItems (Collection) ──┐
│   └── RMAItemDomainObject (n)
│       ├── RMAReasonLangDomainObject (1) ──┐
│       │   └── RMAReasonDomainObject (1)   │
│       └── Artikel (Product) (1)           │
├── RMAReturnAddressDomainObject (1)        │
└── RMAHistoryDomainObject (n)              │
    └── RMAEventDataDomainObject (1)        │
                                            │
RMAReasonDomainObject (1) ─────────────────┘
└── RMAReasonLangDomainObject (n)
```

---

## Wiederverwendbarkeit für Customer Returns Plugin

### ✅ Direkt nutzbar

- **RMADomainObject** - Vollständig einsetzbar
- **RMAItemDomainObject** - Alle Features vorhanden
- **RMAReasonDomainObject** + **RMAReasonLangDomainObject** - Grund-System
- **RMAReturnAddressDomainObject** - Rücksendeadresse
- **RMAHistoryDomainObject** - Änderungsverfolgung

### ⚠️ Zu prüfen

- **RMAItems Collection** - Implementierung analysieren
- **Status-Konstanten** - Wo definiert? (RETURN_STATUS_*)
- **AbstractDomainObject** - Parent-Class-Features

### 🔄 Erweiterungen nötig

Keine! Alle benötigten Felder sind vorhanden:
- ✅ Bestellreferenz (`orderID`, `orderPosID`)
- ✅ Kundenreferenz (`customerID`)
- ✅ Retourengründe (`reasonID`)
- ✅ Gutschrift vs. Rückzahlung (`voucherCredit`)
- ✅ Versandkosten-Erstattung (`refundShipping`)
- ✅ Wawi-Sync (`wawiID`, `synced`)
- ✅ Kommentare (`comment`)
- ✅ Historie (RMAHistory)

---

## Nächste Schritte

1. ✅ **RMAItems Collection analysieren** → Helper/RMAItems.php
2. ⬜ **Status-Konstanten finden** → Suche nach RETURN_STATUS_*
3. ⬜ **AbstractDomainObject analysieren** → Parent-Features
4. ⬜ **Services analysieren** → Wie werden DomainObjects verwendet?

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** Domain Objects sind **vollständig wiederverwendbar** für Customer Returns Plugin
