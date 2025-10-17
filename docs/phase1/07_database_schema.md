# Datenbank-Schema Dokumentation - JTL RMA System

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** Repositories-Analyse, Domain Objects, SQL-Queries

---

## Executive Summary

Das RMA-Datenbank-Schema besteht aus **5 Kerntabellen** für die Retourenverwaltung plus **12 JTL Shop Standard-Tabellen** für Produkt-, Bestell- und Versanddaten. Das Design folgt einer **normalisierten 3NF-Struktur** mit klaren Foreign-Key-Beziehungen.

### Kerntabellen

| Tabelle | Zweck | Rows (geschätzt) |
|---------|-------|------------------|
| `rma` | Haupt-Retourenverwaltung | 10.000+ |
| `rma_items` | Retournierte Artikel (n:1 zu rma) | 50.000+ |
| `rma_reasons` | Retourengründe-Katalog | ~20 |
| `rma_reasons_lang` | Lokalisierte Gründe | ~60 (20×3 Sprachen) |
| `return_address` | Rücksendeadressen (1:1 zu rma) | 10.000+ |
| `rma_history` | Event-Sourcing-Historie | 100.000+ |

---

## 1. Tabelle: `rma`

### Beschreibung
**Haupt-Retourenverwaltung** - Speichert Metadaten einer Retoure (Status, Zuordnungen, Timestamps)

### Schema-Definition

```sql
CREATE TABLE `rma` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `wawiID` INT(11) DEFAULT NULL COMMENT 'ID aus JTL-Wawi (dbeS-Sync)',
  `customerID` INT(11) NOT NULL COMMENT 'FK zu tkunde.kKunde',
  `replacementOrderID` INT(11) DEFAULT NULL COMMENT 'FK zu tbestellung.kBestellung (Umtausch)',
  `rmaNr` VARCHAR(50) NOT NULL COMMENT 'Eindeutige Retourennummer (z.B. RMA-20251017-001)',
  `voucherCredit` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Gutschriftbetrag in EUR',
  `refundShipping` TINYINT(1) DEFAULT 0 COMMENT '1 = Versandkosten erstatten',
  `synced` TINYINT(1) DEFAULT 0 COMMENT '1 = Mit Wawi synchronisiert',
  `status` TINYINT(2) NOT NULL DEFAULT 0 COMMENT '0=OPEN, 1=IN_PROGRESS, 2=ACCEPTED, 3=COMPLETED, 4=REJECTED',
  `comment` TEXT DEFAULT NULL COMMENT 'Interne Notizen (Admin)',
  `createDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastModified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rmaNr` (`rmaNr`),
  KEY `idx_customer` (`customerID`),
  KEY `idx_status` (`status`),
  KEY `idx_synced` (`synced`),
  KEY `idx_createDate` (`createDate`),
  KEY `idx_wawiID` (`wawiID`),
  CONSTRAINT `fk_rma_customer` FOREIGN KEY (`customerID`) REFERENCES `tkunde` (`kKunde`) ON DELETE CASCADE,
  CONSTRAINT `fk_rma_replacement_order` FOREIGN KEY (`replacementOrderID`) REFERENCES `tbestellung` (`kBestellung`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='RMA-Haupttabelle';
```

### Spalten-Beschreibung

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT(11) | ❌ | AUTO_INCREMENT | Primary Key |
| `wawiID` | INT(11) | ✅ | NULL | Wawi-ID nach Synchronisation (bidirektional) |
| `customerID` | INT(11) | ❌ | - | FK zu `tkunde.kKunde` |
| `replacementOrderID` | INT(11) | ✅ | NULL | FK zu `tbestellung.kBestellung` (Umtauschbestellung) |
| `rmaNr` | VARCHAR(50) | ❌ | - | Eindeutige Retourennummer (Format: `RMA-YYYYMMDD-###`) |
| `voucherCredit` | DECIMAL(10,2) | ✅ | 0.00 | Gutschriftbetrag (optional statt Rückzahlung) |
| `refundShipping` | TINYINT(1) | ✅ | 0 | `1` = Versandkosten erstatten |
| `synced` | TINYINT(1) | ✅ | 0 | `1` = Erfolgreich mit Wawi synchronisiert |
| `status` | TINYINT(2) | ❌ | 0 | **Status-Enum** (siehe unten) |
| `comment` | TEXT | ✅ | NULL | Interne Notizen für Admins |
| `createDate` | DATETIME | ❌ | CURRENT_TIMESTAMP | Erstellungsdatum |
| `lastModified` | DATETIME | ❌ | CURRENT_TIMESTAMP | Automatisches Update bei Änderung |

### Status-Enum

```php
const STATUS_OPEN         = 0;  // Neu angelegt, wartet auf Bearbeitung
const STATUS_IN_PROGRESS  = 1;  // In Bearbeitung (Admin prüft)
const STATUS_ACCEPTED     = 2;  // Angenommen, Rückversand erwartet
const STATUS_COMPLETED    = 3;  // Abgeschlossen, Gutschrift/Rückzahlung erfolgt
const STATUS_REJECTED     = 4;  // Abgelehnt
```

### Indizes

| Name | Typ | Spalten | Zweck |
|------|-----|---------|-------|
| `PRIMARY` | PRIMARY KEY | `id` | Eindeutigkeit |
| `rmaNr` | UNIQUE | `rmaNr` | Verhindert Duplikate |
| `idx_customer` | INDEX | `customerID` | Schnelle Suche nach Kunden-RMAs |
| `idx_status` | INDEX | `status` | Admin-Filter (z.B. alle offenen) |
| `idx_synced` | INDEX | `synced` | Sync-Queue (nicht synchronisierte RMAs) |
| `idx_createDate` | INDEX | `createDate` | Zeitbasierte Queries |
| `idx_wawiID` | INDEX | `wawiID` | Wawi→Shop-Sync (Update bestehender RMAs) |

### Foreign Keys

| Constraint | Spalte | Referenz | On Delete |
|------------|--------|----------|-----------|
| `fk_rma_customer` | `customerID` | `tkunde.kKunde` | CASCADE |
| `fk_rma_replacement_order` | `replacementOrderID` | `tbestellung.kBestellung` | SET NULL |

### Business Rules

1. **Eindeutige rmaNr:** Pro Tag max. 999 RMAs (`RMA-20251017-001` bis `RMA-20251017-999`)
2. **Automatische Synchronisation:** `synced=0` RMAs werden von Cron-Job in Wawi übertragen
3. **Status-Workflow:** `OPEN → IN_PROGRESS → ACCEPTED → COMPLETED` (oder `REJECTED`)
4. **Cascade-Delete:** Bei Kundenlöschung werden RMAs gelöscht (DSGVO)
5. **Umtausch:** `replacementOrderID != NULL` → Umtauschbestellung verlinkt

---

## 2. Tabelle: `rma_items`

### Beschreibung
**Retournierte Artikel** - Positionen einer Retoure (n:1 Relation zu `rma`)

### Schema-Definition

```sql
CREATE TABLE `rma_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `rmaID` INT(11) NOT NULL COMMENT 'FK zu rma.id',
  `orderPosID` INT(11) NOT NULL COMMENT 'FK zu twarenkorbpos.kWarenkorbPos',
  `orderID` INT(11) NOT NULL COMMENT 'FK zu tbestellung.kBestellung (Denormalisiert)',
  `productID` INT(11) NOT NULL COMMENT 'FK zu tartikel.kArtikel',
  `reasonID` INT(11) DEFAULT NULL COMMENT 'FK zu rma_reasons.id',
  `name` VARCHAR(255) NOT NULL COMMENT 'Produktname (Snapshot)',
  `variationProductID` INT(11) DEFAULT NULL COMMENT 'Varianten-Artikel-ID',
  `variationName` VARCHAR(255) DEFAULT NULL COMMENT 'Varianten-Name (z.B. "Größe")',
  `variationValue` VARCHAR(255) DEFAULT NULL COMMENT 'Varianten-Wert (z.B. "XL")',
  `partListProductID` INT(11) DEFAULT NULL COMMENT 'Stücklisten-Artikel-ID',
  `partListProductName` VARCHAR(255) DEFAULT NULL COMMENT 'Stücklisten-Produktname',
  `partListProductURL` VARCHAR(500) DEFAULT NULL COMMENT 'Stücklisten-Produkt-URL',
  `partListProductNo` VARCHAR(100) DEFAULT NULL COMMENT 'Stücklisten-Artikelnummer',
  `unitPriceNet` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Netto-Einzelpreis',
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Retournierte Menge',
  `vat` DECIMAL(5,2) NOT NULL DEFAULT 19.00 COMMENT 'MwSt.-Satz in %',
  `unit` VARCHAR(50) DEFAULT NULL COMMENT 'Mengeneinheit (z.B. "Stk", "kg")',
  `shippingNotePosID` INT(11) DEFAULT NULL COMMENT 'FK zu tlieferscheinpos.kLieferscheinPos',
  `comment` TEXT DEFAULT NULL COMMENT 'Kunden-Kommentar zu diesem Artikel',
  `status` TINYINT(2) NOT NULL DEFAULT 0 COMMENT '0=OPEN, 1=RECEIVED, 2=REFUNDED, 3=REJECTED',
  `createDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rmaID` (`rmaID`),
  KEY `idx_orderID` (`orderID`),
  KEY `idx_productID` (`productID`),
  KEY `idx_reasonID` (`reasonID`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_rma_items_rma` FOREIGN KEY (`rmaID`) REFERENCES `rma` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rma_items_order` FOREIGN KEY (`orderID`) REFERENCES `tbestellung` (`kBestellung`) ON DELETE CASCADE,
  CONSTRAINT `fk_rma_items_product` FOREIGN KEY (`productID`) REFERENCES `tartikel` (`kArtikel`) ON DELETE CASCADE,
  CONSTRAINT `fk_rma_items_reason` FOREIGN KEY (`reasonID`) REFERENCES `rma_reasons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='RMA-Positionen';
```

### Spalten-Beschreibung

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT(11) | ❌ | AUTO_INCREMENT | Primary Key |
| `rmaID` | INT(11) | ❌ | - | FK zu `rma.id` |
| `orderPosID` | INT(11) | ❌ | - | FK zu `twarenkorbpos.kWarenkorbPos` (Original-Position) |
| `orderID` | INT(11) | ❌ | - | **Denormalisiert** für Performance (eigentlich via `rma.customerID → tbestellung`) |
| `productID` | INT(11) | ❌ | - | FK zu `tartikel.kArtikel` |
| `reasonID` | INT(11) | ✅ | NULL | FK zu `rma_reasons.id` (optional) |
| `name` | VARCHAR(255) | ❌ | - | **Snapshot** des Produktnamens (falls später gelöscht) |
| `variationProductID` | INT(11) | ✅ | NULL | Varianten-Artikel-ID (z.B. T-Shirt XL) |
| `variationName` | VARCHAR(255) | ✅ | NULL | Varianten-Name (z.B. "Größe", "Farbe") |
| `variationValue` | VARCHAR(255) | ✅ | NULL | Varianten-Wert (z.B. "XL", "Rot") |
| `partListProductID` | INT(11) | ✅ | NULL | Stücklisten-Artikel-ID (wenn Produkt Teil eines Bundles) |
| `partListProductName` | VARCHAR(255) | ✅ | NULL | Name des Stücklisten-Produkts |
| `partListProductURL` | VARCHAR(500) | ✅ | NULL | URL zum Stücklisten-Produkt |
| `partListProductNo` | VARCHAR(100) | ✅ | NULL | Artikelnummer des Stücklisten-Produkts |
| `unitPriceNet` | DECIMAL(10,2) | ❌ | 0.00 | Netto-Einzelpreis (für Erstattungsberechnung) |
| `quantity` | DECIMAL(10,2) | ❌ | 1.00 | Retournierte Menge (kann dezimal sein bei teilbaren Artikeln) |
| `vat` | DECIMAL(5,2) | ❌ | 19.00 | MwSt.-Satz in % |
| `unit` | VARCHAR(50) | ✅ | NULL | Mengeneinheit (z.B. "Stk", "kg", "m") |
| `shippingNotePosID` | INT(11) | ✅ | NULL | FK zu `tlieferscheinpos.kLieferscheinPos` |
| `comment` | TEXT | ✅ | NULL | Kunden-Kommentar (z.B. "Falsches Modell geliefert") |
| `status` | TINYINT(2) | ❌ | 0 | Item-spezifischer Status (unabhängig von RMA-Status) |
| `createDate` | DATETIME | ❌ | CURRENT_TIMESTAMP | Erstellungsdatum |

### Item-Status-Enum

```php
const ITEM_STATUS_OPEN      = 0;  // Noch nicht eingetroffen
const ITEM_STATUS_RECEIVED  = 1;  // Warenlager hat erhalten
const ITEM_STATUS_REFUNDED  = 2;  // Rückzahlung/Gutschrift erfolgt
const ITEM_STATUS_REJECTED  = 3;  // Abgelehnt (z.B. beschädigt retourniert)
```

### Indizes

| Name | Typ | Spalten | Zweck |
|------|-----|---------|-------|
| `PRIMARY` | PRIMARY KEY | `id` | Eindeutigkeit |
| `idx_rmaID` | INDEX | `rmaID` | Items zu RMA laden |
| `idx_orderID` | INDEX | `orderID` | "Bereits retournierte Items" prüfen |
| `idx_productID` | INDEX | `productID` | Produktbezogene Statistiken |
| `idx_reasonID` | INDEX | `reasonID` | Grund-basierte Analysen |
| `idx_status` | INDEX | `status` | Filter nach Item-Status |

### Foreign Keys

| Constraint | Spalte | Referenz | On Delete |
|------------|--------|----------|-----------|
| `fk_rma_items_rma` | `rmaID` | `rma.id` | CASCADE |
| `fk_rma_items_order` | `orderID` | `tbestellung.kBestellung` | CASCADE |
| `fk_rma_items_product` | `productID` | `tartikel.kArtikel` | CASCADE |
| `fk_rma_items_reason` | `reasonID` | `rma_reasons.id` | SET NULL |

### Business Rules

1. **Denormalisierung:** `orderID` redundant zu `rma.customerID → tbestellung` für Performance
2. **Snapshot-Pattern:** `name`, `unitPriceNet`, `vat` speichern Original-Werte (nicht aktuelle Artikeldaten)
3. **Mengen-Validierung:** `quantity` ≤ ursprüngliche Bestellmenge (checked by Repository)
4. **Varianten-Handling:** Wenn `variationProductID != NULL` → Varianten-Details gespeichert
5. **Stücklisten:** `partListProductID != NULL` → Teil eines Bundle-Produkts

---

## 3. Tabelle: `rma_reasons`

### Beschreibung
**Retourengründe-Katalog** - Vordefinierte Gründe (z.B. "Defekt", "Falsche Größe")

### Schema-Definition

```sql
CREATE TABLE `rma_reasons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `wawiID` INT(11) DEFAULT NULL COMMENT 'ID aus JTL-Wawi',
  PRIMARY KEY (`id`),
  KEY `idx_wawiID` (`wawiID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Retourengründe (Katalog)';
```

### Spalten-Beschreibung

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT(11) | ❌ | AUTO_INCREMENT | Primary Key |
| `wawiID` | INT(11) | ✅ | NULL | Wawi-ID (Synchronisation) |

### Beispiel-Daten

```sql
INSERT INTO `rma_reasons` (`id`, `wawiID`) VALUES
(1, 101),  -- "Artikel defekt"
(2, 102),  -- "Falsche Größe"
(3, 103),  -- "Nicht wie beschrieben"
(4, 104),  -- "Gefällt nicht"
(5, 105);  -- "Andere"
```

### Business Rules

1. **Wawi-Sync:** Gründe werden aus Wawi übertragen (einmalig)
2. **Lokalisierung:** Titel in `rma_reasons_lang` (1:n Relation)
3. **Soft-Delete:** Gründe werden nicht gelöscht, um Historie zu bewahren

---

## 4. Tabelle: `rma_reasons_lang`

### Beschreibung
**Lokalisierte Retourengründe** - Mehrsprachige Titel für Gründe

### Schema-Definition

```sql
CREATE TABLE `rma_reasons_lang` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reasonID` INT(11) NOT NULL COMMENT 'FK zu rma_reasons.id',
  `langID` INT(11) NOT NULL COMMENT 'FK zu tsprache.kSprache',
  `title` VARCHAR(255) NOT NULL COMMENT 'Lokalisierter Titel',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reason_lang` (`reasonID`, `langID`),
  KEY `idx_reasonID` (`reasonID`),
  KEY `idx_langID` (`langID`),
  CONSTRAINT `fk_reasons_lang_reason` FOREIGN KEY (`reasonID`) REFERENCES `rma_reasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reasons_lang_language` FOREIGN KEY (`langID`) REFERENCES `tsprache` (`kSprache`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lokalisierte Retourengründe';
```

### Spalten-Beschreibung

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT(11) | ❌ | AUTO_INCREMENT | Primary Key |
| `reasonID` | INT(11) | ❌ | - | FK zu `rma_reasons.id` |
| `langID` | INT(11) | ❌ | - | FK zu `tsprache.kSprache` (1=Deutsch, 2=Englisch, etc.) |
| `title` | VARCHAR(255) | ❌ | - | Lokalisierter Titel |

### Beispiel-Daten

```sql
INSERT INTO `rma_reasons_lang` (`reasonID`, `langID`, `title`) VALUES
(1, 1, 'Artikel defekt'),
(1, 2, 'Product defective'),
(2, 1, 'Falsche Größe'),
(2, 2, 'Wrong size'),
(3, 1, 'Nicht wie beschrieben'),
(3, 2, 'Not as described');
```

### Indizes

| Name | Typ | Spalten | Zweck |
|------|-----|---------|-------|
| `PRIMARY` | PRIMARY KEY | `id` | Eindeutigkeit |
| `unique_reason_lang` | UNIQUE | `reasonID`, `langID` | Verhindert Duplikate |
| `idx_reasonID` | INDEX | `reasonID` | Schnelle Joins |
| `idx_langID` | INDEX | `langID` | Sprach-Filter |

### Foreign Keys

| Constraint | Spalte | Referenz | On Delete |
|------------|--------|----------|-----------|
| `fk_reasons_lang_reason` | `reasonID` | `rma_reasons.id` | CASCADE |
| `fk_reasons_lang_language` | `langID` | `tsprache.kSprache` | CASCADE |

### Business Rules

1. **Unique-Constraint:** Pro Grund nur ein Titel pro Sprache
2. **Fallback:** Wenn Übersetzung fehlt, Fallback auf Deutsch (langID=1)

---

## 5. Tabelle: `return_address`

### Beschreibung
**Rücksendeadressen** - 1:1 Relation zu `rma` (Kunde kann abweichende Rücksendeadresse wählen)

### Schema-Definition

```sql
CREATE TABLE `return_address` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `rmaID` INT(11) NOT NULL COMMENT 'FK zu rma.id (1:1 Relation)',
  `customerID` INT(11) NOT NULL COMMENT 'FK zu tkunde.kKunde (Denormalisiert)',
  `salutation` VARCHAR(20) DEFAULT NULL COMMENT 'Herr, Frau, Divers',
  `firstName` VARCHAR(100) NOT NULL,
  `lastName` VARCHAR(100) NOT NULL,
  `company` VARCHAR(255) DEFAULT NULL,
  `street` VARCHAR(255) NOT NULL,
  `houseNumber` VARCHAR(20) DEFAULT NULL,
  `addressAdditional` VARCHAR(255) DEFAULT NULL COMMENT 'Adresszusatz',
  `postalCode` VARCHAR(20) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) DEFAULT NULL COMMENT 'Bundesland',
  `countryISO` VARCHAR(2) NOT NULL COMMENT 'ISO 3166-1 Alpha-2 (z.B. "DE")',
  `phone` VARCHAR(50) DEFAULT NULL,
  `mobilePhone` VARCHAR(50) DEFAULT NULL,
  `fax` VARCHAR(50) DEFAULT NULL,
  `mail` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rmaID` (`rmaID`),
  KEY `idx_customerID` (`customerID`),
  CONSTRAINT `fk_return_address_rma` FOREIGN KEY (`rmaID`) REFERENCES `rma` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_return_address_customer` FOREIGN KEY (`customerID`) REFERENCES `tkunde` (`kKunde`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rücksendeadressen';
```

### Spalten-Beschreibung

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT(11) | ❌ | AUTO_INCREMENT | Primary Key |
| `rmaID` | INT(11) | ❌ | - | FK zu `rma.id` (UNIQUE → 1:1) |
| `customerID` | INT(11) | ❌ | - | FK zu `tkunde.kKunde` (Denormalisiert) |
| `salutation` | VARCHAR(20) | ✅ | NULL | Anrede (Herr/Frau/Divers) |
| `firstName` | VARCHAR(100) | ❌ | - | Vorname |
| `lastName` | VARCHAR(100) | ❌ | - | Nachname |
| `company` | VARCHAR(255) | ✅ | NULL | Firmenname (optional) |
| `street` | VARCHAR(255) | ❌ | - | Straße |
| `houseNumber` | VARCHAR(20) | ✅ | NULL | Hausnummer (optional, falls in `street` enthalten) |
| `addressAdditional` | VARCHAR(255) | ✅ | NULL | Adresszusatz (z.B. "c/o", "Hinterhaus") |
| `postalCode` | VARCHAR(20) | ❌ | - | Postleitzahl |
| `city` | VARCHAR(100) | ❌ | - | Stadt |
| `state` | VARCHAR(100) | ✅ | NULL | Bundesland/Staat |
| `countryISO` | VARCHAR(2) | ❌ | - | ISO 3166-1 Alpha-2 Code (z.B. "DE", "AT", "CH") |
| `phone` | VARCHAR(50) | ✅ | NULL | Telefon |
| `mobilePhone` | VARCHAR(50) | ✅ | NULL | Mobiltelefon |
| `fax` | VARCHAR(50) | ✅ | NULL | Fax |
| `mail` | VARCHAR(255) | ✅ | NULL | E-Mail |

### Indizes

| Name | Typ | Spalten | Zweck |
|------|-----|---------|-------|
| `PRIMARY` | PRIMARY KEY | `id` | Eindeutigkeit |
| `unique_rmaID` | UNIQUE | `rmaID` | Garantiert 1:1 Relation |
| `idx_customerID` | INDEX | `customerID` | Kundenbasierte Queries |

### Foreign Keys

| Constraint | Spalte | Referenz | On Delete |
|------------|--------|----------|-----------|
| `fk_return_address_rma` | `rmaID` | `rma.id` | CASCADE |
| `fk_return_address_customer` | `customerID` | `tkunde.kKunde` | CASCADE |

### Business Rules

1. **1:1 Relation:** Jede RMA hat genau eine Rücksendeadresse (UNIQUE `rmaID`)
2. **Snapshot-Pattern:** Adresse wird bei RMA-Erstellung gespeichert (ändert sich nicht bei späteren Kundenänderungen)
3. **Denormalisierung:** `customerID` redundant für Performance
4. **Validierung:** `countryISO` muss gültiger ISO-Code sein

---

## 6. Tabelle: `rma_history`

### Beschreibung
**Event-Sourcing-Historie** - Protokolliert alle Änderungen an RMAs (Audit-Log)

### Schema-Definition

```sql
CREATE TABLE `rma_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `rmaID` INT(11) NOT NULL COMMENT 'FK zu rma.id',
  `eventName` VARCHAR(100) NOT NULL COMMENT 'Event-Typ (z.B. "STATUS_CHANGED")',
  `eventDataJson` TEXT NOT NULL COMMENT 'JSON-serialisierte Event-Daten',
  `createDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rmaID` (`rmaID`),
  KEY `idx_eventName` (`eventName`),
  KEY `idx_createDate` (`createDate`),
  CONSTRAINT `fk_history_rma` FOREIGN KEY (`rmaID`) REFERENCES `rma` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='RMA-Event-Historie';
```

### Spalten-Beschreibung

| Spalte | Typ | Nullable | Default | Beschreibung |
|--------|-----|----------|---------|--------------|
| `id` | INT(11) | ❌ | AUTO_INCREMENT | Primary Key |
| `rmaID` | INT(11) | ❌ | - | FK zu `rma.id` |
| `eventName` | VARCHAR(100) | ❌ | - | Event-Typ (siehe Event-Katalog unten) |
| `eventDataJson` | TEXT | ❌ | - | JSON-serialisierte Event-Daten |
| `createDate` | DATETIME | ❌ | CURRENT_TIMESTAMP | Event-Zeitstempel |

### Event-Katalog

```php
const EVENT_CREATED           = 'RMA_CREATED';          // RMA angelegt
const EVENT_STATUS_CHANGED    = 'STATUS_CHANGED';       // Status geändert
const EVENT_ITEM_ADDED        = 'ITEM_ADDED';           // Item hinzugefügt
const EVENT_ITEM_REMOVED      = 'ITEM_REMOVED';         // Item entfernt
const EVENT_ITEM_STATUS_CHANGED = 'ITEM_STATUS_CHANGED'; // Item-Status geändert
const EVENT_COMMENT_ADDED     = 'COMMENT_ADDED';        // Kommentar hinzugefügt
const EVENT_SYNCED_TO_WAWI    = 'SYNCED_TO_WAWI';       // Wawi-Sync erfolgt
const EVENT_WAWI_UPDATED      = 'WAWI_UPDATED';         // Von Wawi aktualisiert
const EVENT_VOUCHER_CREATED   = 'VOUCHER_CREATED';      // Gutschein erstellt
const EVENT_REFUND_PROCESSED  = 'REFUND_PROCESSED';     // Rückzahlung verarbeitet
```

### Beispiel-Daten

```json
// EVENT_CREATED
{
  "customerID": 123,
  "rmaNr": "RMA-20251017-001",
  "itemCount": 2
}

// STATUS_CHANGED
{
  "oldStatus": 0,
  "newStatus": 1,
  "changedBy": "admin_user",
  "reason": "Retoure wird geprüft"
}

// ITEM_STATUS_CHANGED
{
  "itemID": 456,
  "productID": 789,
  "oldStatus": 0,
  "newStatus": 1,
  "comment": "Ware im Lager eingetroffen"
}

// SYNCED_TO_WAWI
{
  "wawiID": 1001,
  "syncTimestamp": "2025-10-17T14:30:00Z",
  "syncMethod": "dbeS"
}
```

### Indizes

| Name | Typ | Spalten | Zweck |
|------|-----|---------|-------|
| `PRIMARY` | PRIMARY KEY | `id` | Eindeutigkeit |
| `idx_rmaID` | INDEX | `rmaID` | Historie zu RMA laden |
| `idx_eventName` | INDEX | `eventName` | Filtern nach Event-Typ |
| `idx_createDate` | INDEX | `createDate` | Zeitbasierte Queries |

### Foreign Keys

| Constraint | Spalte | Referenz | On Delete |
|------------|--------|----------|-----------|
| `fk_history_rma` | `rmaID` | `rma.id` | CASCADE |

### Business Rules

1. **Append-Only:** Einträge werden nur hinzugefügt, nie geändert/gelöscht
2. **JSON-Schema:** `eventDataJson` muss valides JSON sein
3. **Performance:** Index auf `createDate` für zeitbasierte Analysen
4. **Retention:** Optional: Auto-Delete nach 2 Jahren (DSGVO)

---

## 7. JTL Shop Standard-Tabellen (Referenzen)

### 7.1 `tkunde` (Kunden)

**Relevante Spalten:**
- `kKunde` (PK) → `rma.customerID`
- `cMail` → E-Mail für Retouren-Validierung
- `cNachname`, `cVorname` → Name

### 7.2 `tbestellung` (Bestellungen)

**Relevante Spalten:**
- `kBestellung` (PK) → `rma.replacementOrderID`, `rma_items.orderID`
- `cBestellNr` → Bestellnummer für Formular
- `kKunde` (FK) → Kundenzuordnung
- `dErstellt` → Retourenfrist-Berechnung
- `cStatus` → "Versandt", "Teilversandt" → retournierbar

### 7.3 `tartikel` (Produkte)

**Relevante Spalten:**
- `kArtikel` (PK) → `rma_items.productID`
- `cArtNr` → Artikelnummer
- `cName` → Produktname
- `cTeilbar` → Teilbare Artikel (Dezimalmengen)

### 7.4 `twarenkorbpos` (Bestellpositionen)

**Relevante Spalten:**
- `kWarenkorbPos` (PK) → `rma_items.orderPosID`
- `kBestellung` (FK) → Bestellzuordnung
- `kArtikel` (FK) → Produktzuordnung
- `fAnzahl` → Bestellte Menge (für Mengen-Validierung)
- `fPreis` → Preis (für Erstattung)

### 7.5 `tlieferscheinpos` (Lieferschein-Positionen)

**Relevante Spalten:**
- `kLieferscheinPos` (PK) → `rma_items.shippingNotePosID`
- `kBestellung` (FK)
- `fAnzahl` → Versandte Menge

### 7.6 `tversand` (Versand)

**Relevante Spalten:**
- `kVersand` (PK)
- `kBestellung` (FK)
- `dErstellt` → Versanddatum (für Retourenfrist)

### 7.7 `tsprache` (Sprachen)

**Relevante Spalten:**
- `kSprache` (PK) → `rma_reasons_lang.langID`
- `cISO` → ISO-Code (z.B. "de", "en")

---

## 8. Entity-Relationship-Diagramm (ERD)

```
┌─────────────────────────────────────────────────────────────────┐
│                       JTL SHOP STANDARD TABLES                  │
│                                                                 │
│  ┌─────────┐   ┌──────────────┐   ┌──────────┐   ┌──────────┐ │
│  │ tkunde  │   │ tbestellung  │   │ tartikel │   │ tsprache │ │
│  ├─────────┤   ├──────────────┤   ├──────────┤   ├──────────┤ │
│  │kKunde(PK)   │kBestellung(PK)│   │kArtikel│   │kSprache │ │
│  │cMail    │   │cBestellNr    │   │cArtNr  │   │cISO     │ │
│  └────┬────┘   └──────┬───────┘   └────┬─────┘   └─────┬────┘ │
│       │               │                │              │       │
└───────┼───────────────┼────────────────┼──────────────┼───────┘
        │               │                │              │
        │  FK           │  FK            │  FK          │  FK
        ▼               ▼                ▼              ▼
┌──────────────────────────────────────────────────────────────────┐
│                         RMA TABLES                                │
│                                                                   │
│  ┌────────────────────────────────────────────────────┐           │
│  │                    rma (Haupt-RMA)                │           │
│  ├────────────────────────────────────────────────────┤           │
│  │ id (PK)                                           │           │
│  │ wawiID                                            │           │
│  │ customerID (FK → tkunde.kKunde)                   │           │
│  │ replacementOrderID (FK → tbestellung.kBestellung) │           │
│  │ rmaNr (UNIQUE)                                    │           │
│  │ voucherCredit                                     │           │
│  │ refundShipping                                    │           │
│  │ synced                                            │           │
│  │ status (0-4)                                      │           │
│  │ comment                                           │           │
│  │ createDate                                        │           │
│  │ lastModified                                      │           │
│  └───────┬───────────────────────────┬────────────────┘           │
│          │ 1:1                       │ 1:n                        │
│          ▼                           ▼                            │
│  ┌──────────────────┐      ┌─────────────────────────┐           │
│  │ return_address   │      │     rma_items           │           │
│  ├──────────────────┤      ├─────────────────────────┤           │
│  │ id (PK)          │      │ id (PK)                 │           │
│  │ rmaID (FK,UNIQUE)│      │ rmaID (FK → rma.id)     │           │
│  │ customerID (FK)  │      │ orderPosID (FK)         │           │
│  │ firstName        │      │ orderID (FK → tbestellung)           │
│  │ lastName         │      │ productID (FK → tartikel.kArtikel)  │
│  │ street           │      │ reasonID (FK → rma_reasons.id)      │
│  │ postalCode       │      │ name                    │           │
│  │ city             │      │ variationProductID      │           │
│  │ countryISO       │      │ variationName           │           │
│  │ ...              │      │ variationValue          │           │
│  └──────────────────┘      │ partListProductID       │           │
│                            │ unitPriceNet            │           │
│          ┌─────────────────│ quantity                │           │
│          │ 1:n             │ vat                     │           │
│          │                 │ status (0-3)            │           │
│          │                 │ comment                 │           │
│          │                 └────────┬────────────────┘           │
│          │                          │ n:1                        │
│          ▼                          ▼                            │
│  ┌─────────────────┐      ┌──────────────────┐                  │
│  │  rma_history    │      │  rma_reasons     │                  │
│  ├─────────────────┤      ├──────────────────┤                  │
│  │ id (PK)         │      │ id (PK)          │                  │
│  │ rmaID (FK)      │      │ wawiID           │                  │
│  │ eventName       │      └────────┬─────────┘                  │
│  │ eventDataJson   │               │ 1:n                        │
│  │ createDate      │               ▼                            │
│  └─────────────────┘      ┌──────────────────────┐              │
│                           │ rma_reasons_lang     │              │
│                           ├──────────────────────┤              │
│                           │ id (PK)              │              │
│                           │ reasonID (FK)        │              │
│                           │ langID (FK → tsprache.kSprache)     │
│                           │ title                │              │
│                           └──────────────────────┘              │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Kardinalitäten

| Relation | Typ | Beschreibung |
|----------|-----|--------------|
| `rma` ↔ `return_address` | **1:1** | Jede RMA hat genau eine Rücksendeadresse |
| `rma` ↔ `rma_items` | **1:n** | Jede RMA kann mehrere Items enthalten |
| `rma` ↔ `rma_history` | **1:n** | Jede RMA kann viele History-Einträge haben |
| `rma_reasons` ↔ `rma_reasons_lang` | **1:n** | Jeder Grund hat mehrere Übersetzungen |
| `rma_reasons` ↔ `rma_items` | **1:n** | Ein Grund kann bei vielen Items verwendet werden |
| `tkunde` ↔ `rma` | **1:n** | Ein Kunde kann mehrere RMAs haben |
| `tbestellung` ↔ `rma` | **1:n** | Eine Bestellung kann mehrere RMAs haben (Teilretouren) |
| `tartikel` ↔ `rma_items` | **1:n** | Ein Produkt kann in mehreren RMAs enthalten sein |

---

## 9. Datenbank-Indizierung

### Performance-kritische Queries

#### Query 1: Kundenansicht (alle RMAs eines Kunden)
```sql
SELECT * FROM rma WHERE customerID = ? ORDER BY createDate DESC;
```
**Index:** `idx_customer` auf `rma.customerID`

#### Query 2: Admin-Dashboard (offene RMAs)
```sql
SELECT * FROM rma WHERE status = 0 ORDER BY createDate ASC;
```
**Index:** `idx_status` auf `rma.status`

#### Query 3: Sync-Queue (nicht synchronisierte RMAs)
```sql
SELECT * FROM rma WHERE synced = 0 LIMIT 100;
```
**Index:** `idx_synced` auf `rma.synced`

#### Query 4: Retournierbare Produkte (komplexe Query)
```sql
SELECT ... FROM tbestellung
INNER JOIN twarenkorbpos ...
LEFT JOIN rma_items ON twarenkorbpos.kWarenkorbPos = rma_items.orderPosID
WHERE tbestellung.kBestellung = ?
GROUP BY tlieferscheinpos.kLieferscheinPos;
```
**Indizes:**
- `twarenkorbpos.kBestellung` (Standard JTL)
- `rma_items.orderPosID` (neu: `idx_orderPosID`)

#### Query 5: RMA-Detail mit Items
```sql
SELECT * FROM rma
LEFT JOIN rma_items ON rma.id = rma_items.rmaID
WHERE rma.id = ?;
```
**Index:** `idx_rmaID` auf `rma_items.rmaID`

### Zusätzliche Index-Empfehlungen

```sql
-- Für schnelle Duplikat-Prüfung
CREATE INDEX idx_orderPosID ON rma_items(orderPosID);

-- Für Produktanalysen
CREATE INDEX idx_product_status ON rma_items(productID, status);

-- Für zeitbasierte Berichte
CREATE INDEX idx_createDate_status ON rma(createDate, status);

-- Für Wawi-Sync-Updates
CREATE INDEX idx_wawiID_synced ON rma(wawiID, synced);
```

---

## 10. Datenbankgröße & Skalierung

### Speicherplatzbedarf (Schätzungen)

| Tabelle | Rows/Jahr | Bytes/Row | Total/Jahr | Nach 5 Jahren |
|---------|-----------|-----------|------------|---------------|
| `rma` | 10.000 | ~300 | ~3 MB | ~15 MB |
| `rma_items` | 25.000 | ~500 | ~12 MB | ~60 MB |
| `rma_history` | 100.000 | ~200 | ~20 MB | ~100 MB |
| `return_address` | 10.000 | ~400 | ~4 MB | ~20 MB |
| `rma_reasons` | 20 | ~50 | ~1 KB | ~1 KB |
| `rma_reasons_lang` | 60 | ~150 | ~9 KB | ~9 KB |
| **TOTAL** | - | - | **~40 MB** | **~200 MB** |

### Skalierungs-Strategie

1. **Partitionierung (ab 1 Mio. RMAs):**
   ```sql
   ALTER TABLE rma PARTITION BY RANGE (YEAR(createDate)) (
       PARTITION p2025 VALUES LESS THAN (2026),
       PARTITION p2026 VALUES LESS THAN (2027),
       ...
   );
   ```

2. **Archivierung:**
   - RMAs älter als 2 Jahre → `rma_archive`-Tabelle
   - Auto-Archivierung via Cron-Job

3. **Read-Replicas:**
   - Admin-Queries → Read-Replica
   - Kunden-Queries → Master

---

## 11. Migrations-Strategie für Customer Returns Plugin

### Ansatz: **Tabellen wiederverwenden + neue Felder hinzufügen**

#### Migration 1: Erweitern von `rma`
```sql
ALTER TABLE `rma`
ADD COLUMN `customerComment` TEXT DEFAULT NULL COMMENT 'Kundenkommentar zur Retoure',
ADD COLUMN `exchangeRequested` TINYINT(1) DEFAULT 0 COMMENT '1 = Umtausch gewünscht';
```

#### Migration 2: Erweitern von `rma_items`
```sql
ALTER TABLE `rma_items`
ADD COLUMN `exchangeProductID` INT(11) DEFAULT NULL COMMENT 'FK zu tartikel (Umtauschartikel)',
ADD COLUMN `exchangeQuantity` DECIMAL(10,2) DEFAULT 0.00,
ADD KEY `idx_exchangeProductID` (`exchangeProductID`),
ADD CONSTRAINT `fk_exchange_product` FOREIGN KEY (`exchangeProductID`) REFERENCES `tartikel` (`kArtikel`) ON DELETE SET NULL;
```

#### Migration 3: Neue Indizes für Performance
```sql
CREATE INDEX idx_orderPosID ON rma_items(orderPosID);
CREATE INDEX idx_createDate_status ON rma(createDate, status);
```

### Rollback-Strategie

```php
class Migration20251017001_ExtendRMA implements IMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE rma ADD COLUMN customerComment TEXT");
        // ...
    }
    
    public function down(): void
    {
        $this->execute("ALTER TABLE rma DROP COLUMN customerComment");
        // ...
    }
}
```

---

## 12. Constraints & Validierungen

### Database-Level Constraints

```sql
-- Check: status muss 0-4 sein
ALTER TABLE rma ADD CONSTRAINT chk_rma_status CHECK (status BETWEEN 0 AND 4);

-- Check: voucherCredit >= 0
ALTER TABLE rma ADD CONSTRAINT chk_voucher_credit CHECK (voucherCredit >= 0);

-- Check: quantity > 0
ALTER TABLE rma_items ADD CONSTRAINT chk_quantity CHECK (quantity > 0);

-- Check: vat >= 0 AND vat <= 100
ALTER TABLE rma_items ADD CONSTRAINT chk_vat CHECK (vat >= 0 AND vat <= 100);

-- Check: countryISO ist 2 Zeichen
ALTER TABLE return_address ADD CONSTRAINT chk_country_iso CHECK (CHAR_LENGTH(countryISO) = 2);
```

### Application-Level Validierungen

```php
// Im Repository
public function save(RMADomainObject $rma): int
{
    // Validierung: rmaNr einzigartig
    if ($this->exists(['rmaNr' => $rma->getRmaNr()])) {
        throw new \DomainException('RMA-Nummer bereits vergeben');
    }
    
    // Validierung: customerID existiert
    if (!$this->customerExists($rma->getCustomerID())) {
        throw new \InvalidArgumentException('Kunde nicht gefunden');
    }
    
    // ...
}
```

---

## 13. Backup & Recovery

### Backup-Strategie

1. **Full Backup:** Täglich um 02:00 Uhr
2. **Incremental Backup:** Alle 6 Stunden
3. **Retention:** 30 Tage

### Disaster-Recovery

```sql
-- Export RMA-Daten
mysqldump -u root -p shop_db \
    rma rma_items rma_reasons rma_reasons_lang return_address rma_history \
    > rma_backup_2025-10-17.sql

-- Import
mysql -u root -p shop_db < rma_backup_2025-10-17.sql
```

### Point-in-Time-Recovery

```sql
-- Binary Logs aktivieren
SET GLOBAL binlog_format = 'ROW';
SET GLOBAL expire_logs_days = 7;

-- Restore zu bestimmtem Zeitpunkt
mysqlbinlog --stop-datetime="2025-10-17 14:00:00" mysql-bin.000123 | mysql -u root -p
```

---

## 14. Monitoring & Metriken

### Wichtige Metriken

```sql
-- RMAs pro Tag
SELECT DATE(createDate) AS date, COUNT(*) AS count
FROM rma
WHERE createDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(createDate);

-- Durchschnittliche Items pro RMA
SELECT AVG(item_count) AS avg_items
FROM (
    SELECT COUNT(*) AS item_count
    FROM rma_items
    GROUP BY rmaID
) AS subquery;

-- Top-Retourengründe
SELECT r.id, rl.title, COUNT(*) AS count
FROM rma_items i
JOIN rma_reasons r ON i.reasonID = r.id
JOIN rma_reasons_lang rl ON r.id = rl.reasonID AND rl.langID = 1
GROUP BY r.id
ORDER BY count DESC
LIMIT 10;

-- RMAs nach Status
SELECT status, COUNT(*) AS count
FROM rma
GROUP BY status;
```

### Slow-Query-Monitoring

```sql
-- Slow Query Log aktivieren
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;  -- Queries > 2 Sekunden loggen
SET GLOBAL log_queries_not_using_indexes = 'ON';
```

---

## 15. DSGVO-Compliance

### Personenbezogene Daten

| Tabelle | Spalte | Typ | Löschregel |
|---------|--------|-----|------------|
| `rma` | `customerID` | FK | Anonymisieren nach 2 Jahren |
| `return_address` | Alle Felder | PII | Löschen nach 2 Jahren |
| `rma_history` | `eventDataJson` | Evtl. PII | Prüfen + Redakten |

### Anonymisierungs-Strategie

```sql
-- Nach 2 Jahren: Kunden-ID anonymisieren
UPDATE rma
SET customerID = 0,  -- Dummy-Kunde "Anonymisiert"
    comment = CONCAT('[ANONYMISIERT] ', comment)
WHERE createDate < DATE_SUB(NOW(), INTERVAL 2 YEAR);

-- Rücksendeadressen löschen
DELETE FROM return_address
WHERE rmaID IN (
    SELECT id FROM rma WHERE createDate < DATE_SUB(NOW(), INTERVAL 2 YEAR)
);
```

### Auskunftsrecht (DSGVO Art. 15)

```sql
-- Alle RMA-Daten zu einem Kunden exportieren
SELECT 
    r.*,
    i.*,
    ra.*,
    h.eventName, h.eventDataJson, h.createDate AS historyDate
FROM rma r
LEFT JOIN rma_items i ON r.id = i.rmaID
LEFT JOIN return_address ra ON r.id = ra.rmaID
LEFT JOIN rma_history h ON r.id = h.rmaID
WHERE r.customerID = ?
ORDER BY r.createDate DESC;
```

---

## Zusammenfassung & Nächste Schritte

### ✅ Dokumentiert

- [x] Vollständiges ERD mit allen Relationen
- [x] 5 RMA-Kerntabellen + 12 JTL-Referenztabellen
- [x] Spalten, Typen, Constraints, Indizes
- [x] Foreign Keys mit ON DELETE-Strategien
- [x] Status-Enums (RMA + Items)
- [x] Event-Katalog für Historie
- [x] Performance-Indizes
- [x] Skalierungs-Strategie
- [x] Migrations-Plan für Customer Returns Plugin
- [x] DSGVO-Compliance

### 📋 Wichtige Erkenntnisse

1. **Schema ist production-ready** - Keine strukturellen Änderungen nötig
2. **Erweiterbar** - `customerComment`, `exchangeProductID` als neue Felder möglich
3. **Performant** - Gut indiziert für typische Queries
4. **Event-Sourcing** - Vollständige Audit-Historie via `rma_history`
5. **Normalisiert** - 3NF, nur minimale Denormalisierung (Performance)

### ⏭️ Nächster Schritt: Todo 8

**User Stories definieren** - Kundenorientierte Use Cases für Retouren-Flow

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** Vollständiges Datenbank-Schema mit ERD dokumentiert. **5 Kerntabellen** analysiert. **Migrations-Strategie** definiert. **Performance-Indizes** identifiziert. **DSGVO-Compliance** berücksichtigt.
