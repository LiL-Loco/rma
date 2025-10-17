# Konzeptdokument: Customer Returns Plugin für JTL Shop 5

**Projekt:** JTL Customer Returns Plugin  
**Plugin-ID:** `jtl_customer_returns`  
**Version:** 1.0.0  
**Datum:** 17. Oktober 2025  
**Entwicklungsphase:** Phase 1 - Konzeption (Abgeschlossen)

---

## Executive Summary

### Projektziel

Entwicklung eines **vollständigen Retouren-Plugins für JTL Shop 5**, das Kunden ermöglicht, Retouren **ohne Login** über Bestellnummer und E-Mail zu initiieren. Das Plugin integriert sich nahtlos in die bestehende JTL-Infrastruktur und synchronisiert automatisch mit der JTL-Wawi.

### Kernfunktionalität

- ✅ **Gastfreundlicher Retouren-Flow**: Kunden benötigen nur Bestellnummer + E-Mail
- ✅ **Produktauswahl mit Gründen**: Mehrere Artikel retournierbar, Mengen wählbar, Retourengründe pflichtbar
- ✅ **Bidirektionale Wawi-Sync**: Automatische Synchronisation Shop ↔ Wawi über dbeS
- ✅ **E-Mail-Benachrichtigungen**: Bestätigung, Status-Updates, Gutscheine, Rückzahlungen
- ✅ **Admin-Backend**: Retouren-Verwaltung, Statistiken, Konfiguration
- ✅ **Retourenlabel-Integration**: Optionale automatische Label-Erstellung (DHL/DPD/UPS)

### MVP-Scope (Must-have)

**15 User Stories** mit **58 Story Points** ≈ **10-12 Entwicklungstage**

| Feature-Kategorie | Story Points | Priorität |
|-------------------|--------------|-----------|
| Retouren-Flow (Frontend) | 21 SP | Must-have |
| Admin-Bereich | 13 SP | Must-have |
| Wawi-Integration | 13 SP | Must-have |
| E-Mail-System | 8 SP | Must-have |
| Config & Validierung | 3 SP | Must-have |
| **MVP Gesamt** | **58 SP** | - |
| Umtausch-Funktion | 13 SP | Should-have |
| Gutschein-System | 8 SP | Should-have |
| Statistiken | 5 SP | Should-have |
| **Total mit Should-have** | **84 SP** | - |

---

## Architektur-Übersicht

### Technologie-Stack

| Layer | Technologie | Standard |
|-------|-------------|----------|
| **Backend** | PHP 7.4+ | PSR-4, PSR-7, PSR-12 |
| **Framework** | JTL Shop 5 | Plugin-API, dbeS-Sync |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | InnoDB, 3NF-Normalisierung |
| **Frontend** | Smarty 3.x, Bootstrap 4 | Nova-Template kompatibel |
| **JavaScript** | jQuery 3.x, DataTables.js | ES5-Syntax |
| **APIs** | REST-ähnlich (PSR-7 Controllers) | JSON, CSRF-Protection |
| **Sync** | dbeS (JTL Wawi) | XML-basiert, bidirektional |

### Komponenten-Diagramm

```
┌────────────────────────────────────────────────────────────────────┐
│                        JTL Shop 5 Core                             │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │          Customer Returns Plugin (jtl_customer_returns)      │ │
│  ├──────────────────────────────────────────────────────────────┤ │
│  │                                                              │ │
│  │  ┌─────────────┐  ┌──────────────┐  ┌────────────────────┐ │ │
│  │  │  Bootstrap  │  │ Controllers  │  │  Domain Objects    │ │ │
│  │  │  (Lifecycle)│  │ (PSR-7)      │  │  (7 Entities)      │ │ │
│  │  └──────┬──────┘  └──────┬───────┘  └─────────┬──────────┘ │ │
│  │         │                │                    │            │ │
│  │         └────────────────┼────────────────────┘            │ │
│  │                          │                                 │ │
│  │  ┌───────────────────────▼─────────────────────────────┐   │ │
│  │  │              Services Layer                         │   │ │
│  │  │  • ReturnRequestService (Core Logic)               │   │ │
│  │  │  • NotificationService (Emails)                    │   │ │
│  │  │  • SyncService (Wawi-Integration)                  │   │ │
│  │  │  • ShippingLabelService (DHL/DPD/UPS)              │   │ │
│  │  └───────────────────────┬─────────────────────────────┘   │ │
│  │                          │                                 │ │
│  │  ┌───────────────────────▼─────────────────────────────┐   │ │
│  │  │           Repositories Layer                        │   │ │
│  │  │  • RMARepository (Main CRUD)                        │   │ │
│  │  │  • RMAItemRepository (Line Items)                   │   │ │
│  │  │  • RMAReasonRepository (Return Reasons)             │   │ │
│  │  │  • RMAHistoryRepository (Event Sourcing)            │   │ │
│  │  └───────────────────────┬─────────────────────────────┘   │ │
│  │                          │                                 │ │
│  └──────────────────────────┼─────────────────────────────────┘ │
│                             │                                   │
├─────────────────────────────┼───────────────────────────────────┤
│                             │                                   │
│  ┌──────────────────────────▼────────────────────────────────┐  │
│  │                  Database Layer (MySQL)                    │  │
│  │  • rma (Haupttabelle)                                      │  │
│  │  • rma_items (Retourenpositionen)                          │  │
│  │  • rma_reasons (Retourengründe)                            │  │
│  │  • return_address (Rücksendeadressen)                      │  │
│  │  • rma_history (Event-Log)                                 │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│                  External Integrations                           │
├──────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌──────────────────┐  ┌───────────────┐  │
│  │   JTL Wawi      │  │ Shipping APIs    │  │  Email SMTP   │  │
│  │  (dbeS Queue)   │  │ (DHL/DPD/UPS)    │  │  (PHPMailer)  │  │
│  └─────────────────┘  └──────────────────┘  └───────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Plugin-Struktur (Ordnerhierarchie)

```
plugins/jtl_customer_returns/
├── version/
│   └── 100/
│       ├── Bootstrap.php                   # Plugin-Lifecycle
│       ├── frontend/
│       │   ├── ReturnController.php        # Frontend-Routing
│       │   └── templates/
│       │       ├── return_form.tpl         # Schritt 1: Validierung
│       │       ├── return_products.tpl     # Schritt 2: Produktauswahl
│       │       ├── return_summary.tpl      # Schritt 3: Zusammenfassung
│       │       └── return_confirmation.tpl # Schritt 4: Bestätigung
│       ├── adminmenu/
│       │   ├── AdminController.php         # Admin-Backend
│       │   └── templates/
│       │       ├── overview.tpl            # Retouren-Liste
│       │       ├── edit.tpl                # Retouren-Detail
│       │       └── statistics.tpl          # Statistiken
│       ├── Services/
│       │   ├── ReturnRequestService.php    # Business Logic
│       │   ├── NotificationService.php     # E-Mail-Versand
│       │   ├── SyncService.php             # Wawi-Integration
│       │   └── ShippingLabelService.php    # Label-Erstellung
│       ├── Repositories/
│       │   ├── RMARepository.php           # CRUD (RMA)
│       │   ├── RMAItemRepository.php       # CRUD (Items)
│       │   ├── RMAReasonRepository.php     # CRUD (Reasons)
│       │   ├── RMAHistoryRepository.php    # Event-Log
│       │   └── RMAReturnAddressRepository.php
│       ├── DomainObjects/
│       │   ├── RMADomainObject.php         # Haupt-Entity
│       │   ├── RMAItemDomainObject.php     # Line Item
│       │   ├── RMAReasonDomainObject.php   # Retouren-Grund
│       │   ├── RMAHistoryDomainObject.php  # History-Event
│       │   ├── RMAReturnAddressDomainObject.php
│       │   └── dbeS/                       # Wawi-Sync-Objekte
│       │       ├── RMASyncObject.php
│       │       ├── RMAItemSyncObject.php
│       │       └── RMAAddressSyncObject.php
│       ├── Helper/
│       │   ├── RMAHistoryEvents.php        # Event-Konstanten
│       │   └── RMAItems.php                # Item-Helpers
│       ├── Crons/
│       │   └── RMASyncCronJob.php          # Wawi-Sync-Job
│       ├── Migrations/
│       │   ├── Migration20251017100000.php # Initial-Schema
│       │   └── Migration20251017100001.php # Indizes
│       ├── sql/
│       │   └── install.sql                 # Fallback-SQL
│       └── lang/
│           ├── de_DE/                      # Deutsche Übersetzungen
│           └── en_GB/                      # Englische Übersetzungen
├── info.xml                                # Plugin-Manifest
├── frontend/
│   ├── css/
│   │   └── customer_returns.css           # Plugin-CSS
│   └── js/
│       └── customer_returns.js            # Plugin-JS
└── Licences/
    └── LICENSE.txt
```

**Namespace-Konvention:** `Plugin\jtl_customer_returns\`  
**Autoloading:** PSR-4 via Composer (`composer.json` im Plugin-Root)

---

## Datenbank-Design

### Entity-Relationship-Diagramm

```
┌──────────────────────┐
│     tbestellung      │ (JTL Standard)
│  kBestellung (PK)    │
│  cBestellNr          │
│  kKunde (FK)         │
│  dErstellt           │
│  dVersandt           │
└───────┬──────────────┘
        │
        │ 1:N
        │
┌───────▼──────────────────────────────────────────────────────────┐
│                           rma                                    │
├──────────────────────────────────────────────────────────────────┤
│  id (PK, BIGINT AUTO_INCREMENT)                                  │
│  rmaNr (VARCHAR(50) UNIQUE, e.g. "RMA-2025-00123")               │
│  orderID (BIGINT FK → tbestellung.kBestellung)                   │
│  customerID (INT FK → tkunde.kKunde, NULL für Gäste)             │
│  status (TINYINT: 0=OPEN, 1=IN_PROGRESS, 2=ACCEPTED, 3=DONE)    │
│  totalGross (DECIMAL(10,2), Retourenwert brutto)                 │
│  returnAddressID (INT FK → return_address.id, NULL)              │
│  wawiID (INT NULL, Wawi-RMA-ID nach Sync)                        │
│  synced (TINYINT(1), 0=unsync, 1=synced)                         │
│  labelPath (VARCHAR(255) NULL, Pfad zu Label-PDF)                │
│  createDate (DATETIME NOT NULL)                                  │
│  updateDate (DATETIME NOT NULL)                                  │
│  lastSyncDate (DATETIME NULL)                                    │
│  INDEX idx_orderID (orderID)                                     │
│  INDEX idx_customerID (customerID)                               │
│  INDEX idx_status (status)                                       │
│  INDEX idx_synced (synced)                                       │
│  UNIQUE KEY uk_rmaNr (rmaNr)                                     │
└────────┬──────────────────────────────────┬────────────────────┬──┘
         │                                  │                    │
         │ 1:N                              │ 1:N                │ 1:1
         │                                  │                    │
┌────────▼─────────────────────┐  ┌─────────▼─────────────┐  ┌───▼────────────────┐
│       rma_items              │  │     rma_history       │  │  return_address    │
├──────────────────────────────┤  ├───────────────────────┤  ├────────────────────┤
│  id (PK)                     │  │  id (PK)              │  │  id (PK)           │
│  rmaID (FK → rma.id)         │  │  rmaID (FK → rma.id)  │  │  customerID (FK)   │
│  productID (FK → tartikel)   │  │  event (VARCHAR(50))  │  │  salutation        │
│  variationID (FK, NULL)      │  │  eventData (JSON)     │  │  firstName         │
│  quantity (INT)              │  │  createdAt (DATETIME) │  │  lastName          │
│  reasonID (FK → rma_reasons) │  │  createdBy (INT)      │  │  street            │
│  itemStatus (TINYINT)        │  │  INDEX idx_rmaID      │  │  zip               │
│  refundAmount (DECIMAL)      │  └───────────────────────┘  │  city              │
│  comment (TEXT NULL)         │                             │  country           │
│  INDEX idx_rmaID             │                             │  phone (NULL)      │
│  INDEX idx_productID         │                             └────────────────────┘
└──────────┬───────────────────┘
           │
           │ N:1
           │
┌──────────▼──────────────────────────────┐
│            rma_reasons                  │
├─────────────────────────────────────────┤
│  id (PK)                                │
│  ISO (VARCHAR(3), e.g. "GER", "ENG")    │
│  reason (VARCHAR(255), "Defekt", etc.)  │
│  sortOrder (INT)                        │
│  active (TINYINT(1), 0=inaktiv)         │
│  INDEX idx_ISO (ISO)                    │
│  INDEX idx_active (active)              │
└─────────────────────────────────────────┘
```

### Tabellen-Übersicht

| Tabelle | Zweck | Anzahl Spalten | Indizes |
|---------|-------|----------------|---------|
| `rma` | Haupt-Retourentabelle | 14 | 5 (PK, orderID, customerID, status, synced, UNIQUE rmaNr) |
| `rma_items` | Retourenpositionen (Line Items) | 10 | 3 (PK, rmaID, productID) |
| `rma_reasons` | Mehrsprachige Retourengründe | 5 | 3 (PK, ISO, active) |
| `return_address` | Rücksendeadressen (Kundenadressen) | 11 | 2 (PK, customerID) |
| `rma_history` | Event-Sourcing Log | 5 | 2 (PK, rmaID) |

### Migration-Strategie

**Migrations-Klassen** (PSR-4):
```php
// Migrations/Migration20251017100000.php
class Migration20251017100000 extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("CREATE TABLE rma (...)");
        $this->execute("CREATE TABLE rma_items (...)");
        $this->execute("CREATE TABLE rma_reasons (...)");
        $this->execute("CREATE TABLE return_address (...)");
        $this->execute("CREATE TABLE rma_history (...)");
    }
    
    public function down(): void
    {
        $this->execute("DROP TABLE rma_history");
        $this->execute("DROP TABLE return_address");
        $this->execute("DROP TABLE rma_items");
        $this->execute("DROP TABLE rma_reasons");
        $this->execute("DROP TABLE rma");
    }
}
```

**Fallback:** `sql/install.sql` für Shops ohne Migration-Support

---

## API-Spezifikation

### REST-ähnliche Endpunkte (19 Total)

#### Frontend-Routen (7)

| Method | Route | Controller-Action | Zweck |
|--------|-------|-------------------|-------|
| GET | `/retoure` | `ReturnController@index` | Formular anzeigen (Schritt 1) |
| POST | `/retoure/validate` | `ReturnController@validateOrder` | AJAX: Bestellung validieren |
| GET | `/retoure/products` | `ReturnController@selectProducts` | Produktauswahl (Schritt 2) |
| GET | `/retoure/summary` | `ReturnController@summary` | Zusammenfassung (Schritt 3) |
| POST | `/retoure/submit` | `ReturnController@submit` | Retoure abschicken |
| GET | `/retoure/confirmation/{rmaID}` | `ReturnController@confirmation` | Bestätigung (Schritt 4) |
| GET | `/kundenkonto/retouren` | `ReturnController@myReturns` | Meine Retouren (Login) |

**CSRF-Protection:** Alle POST/PUT/DELETE-Requests erfordern Token

#### AJAX-Endpunkte (5)

| Route | Zweck | Response |
|-------|-------|----------|
| `/retoure/validate` | Bestellung validieren | `{valid: true, orderData: {...}}` |
| `/retoure/reasons` | Retourengründe laden | `[{id: 1, reason: "Defekt"}, ...]` |
| `/retoure/products/{orderID}` | Retournierbare Produkte | `[{productID: 123, name: "...", qty: 2}, ...]` |
| `/retoure/calculate-refund` | Rückerstattungsbetrag berechnen | `{total: 49.99, shipping: 4.90, refund: 54.89}` |
| `/admin/plugin/returns/status` | Admin: Status ändern | `{success: true, newStatus: 2}` |

#### Admin-Endpunkte (6)

| Route | Zweck |
|-------|-------|
| `/admin/plugin/jtl_customer_returns/overview` | Retouren-Liste |
| `/admin/plugin/jtl_customer_returns/edit?rmaID=123` | Retouren-Detail |
| `/admin/plugin/jtl_customer_returns/statistics` | Statistiken |
| `/admin/plugin/jtl_customer_returns/settings` | Einstellungen |
| `/admin/plugin/jtl_customer_returns/export` | CSV-Export |
| `/admin/plugin/jtl_customer_returns/create-label` | Retourenlabel erstellen |

#### Cron-Endpunkt (1)

| Route | Zweck | Frequenz |
|-------|-------|----------|
| `/cron/rma-sync` | Wawi-Sync ausführen | Alle 15 Min. (konfigurierbar) |

### Request/Response-Beispiel

**POST /retoure/submit**

Request:
```json
{
    "orderID": 12345,
    "items": [
        {
            "productID": 678,
            "variationID": null,
            "quantity": 1,
            "reasonID": 2,
            "comment": "Artikel defekt bei Lieferung"
        },
        {
            "productID": 679,
            "variationID": 123,
            "quantity": 2,
            "reasonID": 1,
            "comment": "Größe passt nicht"
        }
    ],
    "returnAddressID": 5,
    "csrfToken": "abc123..."
}
```

Response (Success):
```json
{
    "success": true,
    "rmaID": 42,
    "rmaNr": "RMA-2025-00042",
    "redirectUrl": "/retoure/confirmation/42"
}
```

Response (Error):
```json
{
    "success": false,
    "error": "ORDER_NOT_RETURNABLE",
    "message": "Bestellung kann nicht mehr retourniert werden (Frist abgelaufen).",
    "details": {
        "orderDate": "2025-01-15",
        "returnDeadline": "2025-01-29",
        "today": "2025-10-17"
    }
}
```

---

## Prozessflow-Diagramme

### Retouren-Flow (Frontend)

```
┌──────────────────────┐
│  1. Formular         │
│  (Bestellnr + Email) │
└──────────┬───────────┘
           │
           │ POST /retoure/validate (AJAX)
           │
           ▼
┌──────────────────────┐      ┌────────────────────────┐
│  2. Validierung      │ ─NO──▶│ Fehler: "Bestellung   │
│  - Order exists?     │      │  nicht gefunden"       │
│  - Email matches?    │      └────────────────────────┘
│  - Within period?    │
└──────────┬───────────┘
           │ YES
           │
           ▼
┌──────────────────────────────────────────────────────┐
│  3. Produktauswahl                                   │
│  - DataTable: Retournierbare Artikel                 │
│  - Checkboxen: Artikel wählen                        │
│  - Mengen-Input: Anzahl (max. bestellte Menge)       │
│  - Dropdown: Retouren-Grund (aus rma_reasons)        │
│  - Textarea: Kommentar (optional)                    │
│  - Dropdown: Rücksendeadresse (Kundenkonten)         │
└──────────┬───────────────────────────────────────────┘
           │
           │ GET /retoure/summary
           │
           ▼
┌──────────────────────────────────────────────────────┐
│  4. Zusammenfassung                                  │
│  - Gewählte Artikel anzeigen                         │
│  - Retourengründe anzeigen                           │
│  - Rücksendeadresse anzeigen                         │
│  - Berechneter Rückerstattungsbetrag                 │
│  - [Bestätigen]-Button                               │
└──────────┬───────────────────────────────────────────┘
           │
           │ POST /retoure/submit
           │
           ▼
┌──────────────────────────────────────────────────────┐
│  5. Backend-Verarbeitung                             │
│  - RMADomainObject erstellen                         │
│  - RMAItemDomainObjects erstellen                    │
│  - rma_history Event: "RMA_CREATED"                  │
│  - E-Mail senden (Bestätigung)                       │
│  - Optional: Retourenlabel erstellen                 │
│  - Wawi-Sync-Queue einreihen                         │
└──────────┬───────────────────────────────────────────┘
           │
           │ Redirect
           │
           ▼
┌──────────────────────────────────────────────────────┐
│  6. Bestätigungsseite                                │
│  - Retourennummer: RMA-2025-00042                    │
│  - Erfolgsmeldung                                    │
│  - Informationen zum Ablauf                          │
│  - Link zum Retourenlabel (Download)                 │
│  - Rücksendeadresse anzeigen                         │
└──────────────────────────────────────────────────────┘
```

### Wawi-Synchronisation (Bidirektional)

```
┌────────────────────────────────────────────────────────────────┐
│                      JTL Shop 5                                │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  1. RMA erstellt (ReturnRequestService)                        │
│     └─▶ SyncService->syncToWawi()                             │
│         └─▶ RMASyncObject erstellen                           │
│             └─▶ dbeS-Queue->add('rma', $syncObject)           │
│                                                                │
└────────────────────────┬───────────────────────────────────────┘
                         │
                         │ XML via dbeS
                         │
┌────────────────────────▼───────────────────────────────────────┐
│                      JTL Wawi                                  │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  2. dbeS empfängt RMA-Sync-Objekt                              │
│     └─▶ RMA in Wawi anlegen                                   │
│         └─▶ Wawi-RMA-ID generieren (z.B. 99123)               │
│             └─▶ Status in Wawi setzen: "Wartet auf Ware"     │
│                                                                │
│  3. Wawi verarbeitet Retoure                                   │
│     └─▶ Status ändern: "Ware eingegangen"                    │
│         └─▶ Rückzahlung auslösen                              │
│             └─▶ dbeS sendet Update an Shop                    │
│                                                                │
└────────────────────────┬───────────────────────────────────────┘
                         │
                         │ XML via dbeS
                         │
┌────────────────────────▼───────────────────────────────────────┐
│                      JTL Shop 5                                │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  4. Event: 'dbes.sync.received' (type='rma')                   │
│     └─▶ RMA aktualisieren                                     │
│         ├─▶ wawiID = 99123                                    │
│         ├─▶ status = 3 (COMPLETED)                            │
│         └─▶ rma_history Event: "WAWI_UPDATED"                 │
│                                                                │
│  5. NotificationService->sendStatusUpdate()                    │
│     └─▶ E-Mail an Kunden: "Ihre Retoure wurde bearbeitet"    │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## Implementierungs-Roadmap

### Phase 2: Plugin-Grundstruktur (3 Tage)

**Ziel:** Lauffähiges Basis-Plugin mit DB-Schema

| Task | Dauer | Output |
|------|-------|--------|
| info.xml erstellen (Manifest, Settings, Email-Templates) | 2h | `info.xml` (150+ Zeilen) |
| Bootstrap.php implementieren (Lifecycle, Event-Listener) | 3h | `Bootstrap.php` (300+ Zeilen) |
| Migrations erstellen (5 Tabellen) | 2h | `Migration20251017100000.php` |
| Domain Objects implementieren (7 Klassen) | 4h | `DomainObjects/*.php` |
| Repositories implementieren (6 Klassen) | 5h | `Repositories/*.php` |
| Plugin installieren & testen | 2h | DB-Schema validiert |

**Deliverables:**
- ✅ Plugin installierbar
- ✅ Datenbank-Schema vorhanden
- ✅ Admin-Menü sichtbar
- ✅ Settings konfigurierbar

---

### Phase 3: Backend-Services (4 Tage)

**Ziel:** Business Logic & API-Endpunkte

| Task | Dauer | Output |
|------|-------|--------|
| ReturnRequestService (Core Logic) | 8h | Validierung, CRUD, Retournierbare Produkte |
| NotificationService (E-Mail) | 4h | 4 E-Mail-Templates rendern & senden |
| SyncService (Wawi-Integration) | 6h | dbeS-Queue, RMASyncObject, Event-Listener |
| ShippingLabelService (Optional) | 4h | DHL/DPD-API-Integration |
| RMAHistoryService (Event-Log) | 2h | Event-Sourcing, JSON-Serialisierung |
| Unit-Tests (PHPUnit) | 6h | 80%+ Code Coverage |

**Deliverables:**
- ✅ Services testbar via PHPUnit
- ✅ API-Endpunkte implementiert (Controller fehlen noch)
- ✅ Wawi-Sync funktional (Test-Daten)

---

### Phase 4: Frontend-Controller & Templates (5 Tage)

**Ziel:** Vollständiger Retouren-Flow für Kunden

| Task | Dauer | Output |
|------|-------|--------|
| ReturnController (Frontend-Routing) | 6h | 7 Controller-Actions |
| return_form.tpl (Schritt 1: Validierung) | 3h | Formular + Ajax-Validierung |
| return_products.tpl (Schritt 2: Produktauswahl) | 6h | DataTable, Checkboxen, Dropdowns |
| return_summary.tpl (Schritt 3: Zusammenfassung) | 3h | Übersicht + Bestätigen |
| return_confirmation.tpl (Schritt 4: Bestätigung) | 2h | Erfolgsmeldung + Label-Download |
| customer_returns.js (Frontend-JS) | 4h | Ajax-Calls, Validierung, DataTables |
| customer_returns.css (Frontend-CSS) | 2h | Responsive Design, Bootstrap-Erweiterung |
| E2E-Tests (Browser) | 4h | Selenium/Playwright |

**Deliverables:**
- ✅ Kompletter Retouren-Flow funktional
- ✅ Mobile-Responsive (Bootstrap 4)
- ✅ CSRF-Protection aktiv
- ✅ Fehlerbehandlung implementiert

---

### Phase 5: Admin-Backend (3 Tage)

**Ziel:** Retouren-Verwaltung für Shop-Admins

| Task | Dauer | Output |
|------|-------|--------|
| AdminController (Backend-Routing) | 4h | Übersicht, Detail, Settings |
| overview.tpl (Retouren-Liste) | 4h | DataTable mit Filter/Suche |
| edit.tpl (Retouren-Detail) | 5h | Status ändern, Kommentare, Historie |
| statistics.tpl (Statistiken) | 3h | Charts (Chart.js), KPIs |
| Admin-JS (DataTables, Ajax) | 3h | Interaktive Tabellen |
| Permissions & Rechte | 2h | Admin-Rollen validieren |

**Deliverables:**
- ✅ Admins können Retouren verwalten
- ✅ Status-Updates triggern E-Mails
- ✅ Statistiken visualisiert

---

### Phase 6: Wawi-Integration & Cron-Jobs (2 Tage)

**Ziel:** Automatische Synchronisation mit JTL-Wawi

| Task | Dauer | Output |
|------|-------|--------|
| dbeS Sync Objects finalisieren (5 Klassen) | 3h | RMASyncObject, RMAItemSyncObject, etc. |
| RMASyncCronJob implementieren | 3h | Alle 15 Min. unsynchronisierte RMAs syncen |
| Event-Listener: dbes.sync.received | 2h | Bidirektionale Updates verarbeiten |
| Wawi-Sync-Tests (Mock-Objekte) | 4h | Sync-Flow validieren |
| Error-Handling & Retry-Logik | 2h | 3 Versuche, Admin-Benachrichtigung |

**Deliverables:**
- ✅ RMAs automatisch in Wawi synchronisiert
- ✅ Status-Updates bidirektional
- ✅ Fehlerbehandlung robust

---

### Phase 7: Testing & Bugfixing (3 Tage)

**Ziel:** Production-Ready Plugin

| Task | Dauer | Output |
|------|-------|--------|
| Unit-Tests vervollständigen | 6h | 90%+ Coverage |
| Integration-Tests (kompletter Flow) | 6h | Order → RMA → Wawi → Email |
| Browser-Tests (Chrome, Firefox, Safari, Edge) | 4h | Cross-Browser-Kompatibilität |
| Performance-Tests (1000+ Bestellungen) | 2h | Queries optimieren |
| Security-Audit (CSRF, SQL-Injection, XSS) | 4h | Penetration-Tests |
| Bugfixing & Refactoring | 4h | Code-Qualität verbessern |

**Deliverables:**
- ✅ Plugin stabil & sicher
- ✅ Performance optimiert
- ✅ Alle Tests grün

---

### Phase 8: Dokumentation & Deployment (2 Tage)

**Ziel:** Bereitstellung für Produktion

| Task | Dauer | Output |
|------|-------|--------|
| README.md (Installation, Konfiguration) | 3h | Markdown-Dokumentation |
| Admin-Handbuch (Screenshots) | 3h | PDF-Guide für Admins |
| Entwickler-Dokumentation (API-Docs) | 3h | PHPDoc, Swagger-ähnlich |
| Deployment-Checkliste | 1h | Server-Anforderungen, .env |
| Plugin-Paket erstellen (ZIP) | 1h | jtl_customer_returns_v1.0.0.zip |
| Deployment auf Staging-Server | 3h | Test-Installation |

**Deliverables:**
- ✅ Plugin produktionsreif
- ✅ Dokumentation vollständig
- ✅ Deployment-Paket verfügbar

---

### Timeline-Übersicht (MVP)

| Phase | Dauer | Start | Ende |
|-------|-------|-------|------|
| Phase 1: Konzeption | 3 Tage | Tag 1 | Tag 3 ✅ |
| Phase 2: Plugin-Grundstruktur | 3 Tage | Tag 4 | Tag 6 |
| Phase 3: Backend-Services | 4 Tage | Tag 7 | Tag 10 |
| Phase 4: Frontend-Templates | 5 Tage | Tag 11 | Tag 15 |
| Phase 5: Admin-Backend | 3 Tage | Tag 16 | Tag 18 |
| Phase 6: Wawi-Integration | 2 Tage | Tag 19 | Tag 20 |
| Phase 7: Testing | 3 Tage | Tag 21 | Tag 23 |
| Phase 8: Dokumentation | 2 Tage | Tag 24 | Tag 25 |

**Gesamtdauer:** **25 Arbeitstage** (≈ 5 Wochen)

---

## Qualitätssicherung

### Testing-Strategie

#### Unit-Tests (PHPUnit)

**Coverage-Ziel:** 90%+

**Test-Kategorien:**

1. **Services:**
   ```php
   // tests/Services/ReturnRequestServiceTest.php
   class ReturnRequestServiceTest extends TestCase
   {
       public function testValidateOrderAccess_ValidEmail_ReturnsTrue()
       public function testValidateOrderAccess_InvalidEmail_ReturnsFalse()
       public function testGetReturnableProducts_WithinPeriod_ReturnsProducts()
       public function testGetReturnableProducts_OutsidePeriod_ReturnsEmpty()
       public function testCreateReturnRequest_ValidData_CreatesRMA()
       public function testCreateReturnRequest_InvalidData_ThrowsException()
   }
   ```

2. **Repositories:**
   ```php
   // tests/Repositories/RMARepositoryTest.php
   class RMARepositoryTest extends TestCase
   {
       public function testSave_NewRMA_ReturnsID()
       public function testGetReturns_FilterByStatus_ReturnsFiltered()
       public function testUpdate_ExistingRMA_UpdatesRecord()
       public function testDelete_ExistingRMA_RemovesRecord()
   }
   ```

3. **Domain Objects:**
   ```php
   // tests/DomainObjects/RMADomainObjectTest.php
   class RMADomainObjectTest extends TestCase
   {
       public function testGenerateRmaNr_ReturnsUniqueFormat()
       public function testSetStatus_ValidStatus_SetsStatus()
       public function testSetStatus_InvalidStatus_ThrowsException()
       public function testGetTotalGross_WithItems_CalculatesCorrectly()
   }
   ```

#### Integration-Tests

**Kompletter Retouren-Flow:**

```php
// tests/Integration/ReturnFlowTest.php
class ReturnFlowTest extends IntegrationTestCase
{
    public function testCompleteReturnFlow()
    {
        // 1. Bestellung erstellen (Test-Daten)
        $order = $this->createTestOrder();
        
        // 2. Retoure initiieren
        $response = $this->post('/retoure/validate', [
            'orderNo' => $order->cBestellNr,
            'email' => $order->cMail
        ]);
        $this->assertResponseOk($response);
        
        // 3. Produkte auswählen
        $response = $this->post('/retoure/submit', [
            'orderID' => $order->kBestellung,
            'items' => [
                ['productID' => 123, 'quantity' => 1, 'reasonID' => 2]
            ]
        ]);
        $this->assertResponseRedirect($response);
        
        // 4. RMA in DB prüfen
        $rma = $this->db->query("SELECT * FROM rma WHERE orderID = ?", [$order->kBestellung])->fetch();
        $this->assertNotNull($rma);
        $this->assertEquals(0, $rma->status); // OPEN
        
        // 5. E-Mail versendet prüfen
        $this->assertEmailSent('rma_confirmation', $order->cMail);
        
        // 6. Wawi-Sync-Queue prüfen
        $syncQueue = $this->db->query("SELECT * FROM dbes_queue WHERE type = 'rma'")->fetch();
        $this->assertNotNull($syncQueue);
    }
}
```

#### Browser-Tests (E2E)

**Playwright/Selenium:**

```javascript
// tests/E2E/return_flow.spec.js
describe('Return Flow', () => {
    it('should complete full return process', async () => {
        // 1. Navigate to return form
        await page.goto('/retoure');
        
        // 2. Enter order details
        await page.fill('#order_number', 'ORD-2025-00123');
        await page.fill('#email', 'test@example.com');
        await page.click('#submit_btn');
        
        // 3. Wait for product selection page
        await page.waitForSelector('.product-table');
        
        // 4. Select products
        await page.check('#product_123');
        await page.selectOption('#reason_123', '2');
        await page.click('#next_btn');
        
        // 5. Confirm summary
        await page.waitForSelector('.summary-page');
        await page.click('#confirm_btn');
        
        // 6. Verify confirmation
        await page.waitForSelector('.confirmation-message');
        const rmaNumber = await page.textContent('.rma-number');
        expect(rmaNumber).toMatch(/RMA-2025-\d+/);
    });
});
```

#### Performance-Tests

**Ziele:**

- **Produktlisten-Query:** < 500ms (bei 1000+ Bestellungen)
- **RMA-Erstellung:** < 200ms
- **Wawi-Sync:** < 1s pro RMA
- **Page Load (Frontend):** < 2s

**Tools:**
- JMeter (Load Testing)
- MySQL EXPLAIN (Query-Analyse)
- Xdebug Profiler

---

## Sicherheitskonzept

### OWASP Top 10 - Mitigation

| Risiko | Maßnahme | Implementation |
|--------|----------|----------------|
| **SQL-Injection** | Prepared Statements | `$db->query("SELECT * FROM rma WHERE id = ?", [$id])` |
| **XSS** | Template-Escaping | `{$variable\|escape:'html'}` (Smarty) |
| **CSRF** | Token-Validierung | `$this->validateToken($postData['csrfToken'])` |
| **Broken Auth** | JTL Session-Handling | `Shop::Container()->getCustomer()->isLoggedIn()` |
| **Sensitive Data** | DSGVO-Anonymisierung | `anonymizeCustomerRMAs()` bei Kundenlöschung |
| **Access Control** | Order-Email-Validierung | `validateOrderAccess($orderNo, $email)` |
| **Security Misconfig** | Config-Validierung | Admin-Settings-Sanitierung |
| **Insecure Deserialization** | JSON-Validierung | `json_decode($data, true, 512, JSON_THROW_ON_ERROR)` |
| **Logging** | Error-Logging | `Shop::Container()->getLogService()->error()` |
| **Known Vulnerabilities** | Composer Updates | Regelmäßige Dependency-Updates |

### DSGVO-Compliance

**Personenbezogene Daten:**

| Feld | Zweck | Speicherdauer | Anonymisierung |
|------|-------|---------------|----------------|
| `customerID` | RMA-Zuordnung | Gesetzliche Aufbewahrungsfrist | Bei Kundenlöschung → 0 |
| `return_address.*` | Versandlogistik | Bis RMA abgeschlossen | Nach 30 Tagen löschen |
| `rma_history.eventData` | Audit-Trail | 2 Jahre | Namen entfernen |
| E-Mail-Adressen | Benachrichtigung | In E-Mail-Templates nicht persistent | - |

**Event-Listener:**
```php
// In Bootstrap.php
$dispatcher->listen('shop.customer.deleted', function($event) {
    $this->anonymizeCustomerRMAs($event['customerID']);
});
```

---

## Konfigurationsoptionen (info.xml)

### 11 Settings in 6 Kategorien

| Setting | Typ | Default | Validation |
|---------|-----|---------|------------|
| **Retourenbedingungen** |
| `return_period_days` | Number | 14 | Min: 1, Max: 365 |
| `auto_refund_shipping` | Checkbox | On | - |
| **Retourenlabel** |
| `auto_create_label` | Checkbox | Off | - |
| `shipping_provider` | Selectbox | dhl | dhl, dpd, ups, hermes |
| `shipping_api_key` | Text | '' | Required wenn auto_create_label=On |
| **Produktausschlüsse** |
| `excluded_categories` | Text | '' | Comma-separated IDs |
| `excluded_product_nos` | Textarea | '' | Comma-separated |
| **Benachrichtigungen** |
| `admin_notification_email` | Text | '' | Email-Format |
| `notify_admin_on_new_rma` | Checkbox | On | - |
| **Wawi-Sync** |
| `wawi_sync_interval` | Number | 15 | Min: 5 (Minuten) |
| `wawi_auto_sync` | Checkbox | On | - |
| **Rücksendeadresse** |
| `shop_return_address` | Textarea | '' | Multiline |

---

## Abnahmekriterien (Definition of Done)

### MVP-Release v1.0.0

**Functional Requirements:**

- ✅ Kunden können Retouren **ohne Login** anlegen (Bestellnr + E-Mail)
- ✅ Produktauswahl mit **Mengen** und **Retourengründen**
- ✅ **E-Mail-Bestätigung** mit Retourennummer
- ✅ **Wawi-Sync** bidirektional funktional
- ✅ **Admin-Backend** zur Retouren-Verwaltung
- ✅ **Status-Updates** per E-Mail
- ✅ **Retourenlabel-Download** (optional, DHL)

**Non-Functional Requirements:**

- ✅ **Performance:** < 2s Page Load, < 500ms DB-Queries
- ✅ **Security:** OWASP Top 10 mitigiert, CSRF-Protection
- ✅ **DSGVO:** Anonymisierung bei Kundenlöschung
- ✅ **Browser-Support:** Chrome, Firefox, Safari, Edge (aktuelle Versionen)
- ✅ **Mobile-Responsive:** Bootstrap 4, Touch-optimiert
- ✅ **Code-Qualität:** PSR-12, 90%+ Test Coverage
- ✅ **Dokumentation:** README.md, Admin-Handbuch, API-Docs

**Testing:**

- ✅ **Unit-Tests:** PHPUnit, 90%+ Coverage
- ✅ **Integration-Tests:** Kompletter Flow getestet
- ✅ **Browser-Tests:** Playwright/Selenium
- ✅ **Performance-Tests:** JMeter Load Testing
- ✅ **Security-Audit:** Penetration-Tests bestanden

---

## Referenzen & Ressourcen

### Dokumentation

| Quelle | URL | Zweck |
|--------|-----|-------|
| JTL Shop Plugin-Docs | https://jtl-devguide.readthedocs.io/projects/jtl-shop/de/latest/shop_plugins/ | Plugin-Entwicklung |
| JTL Demo-Plugin | https://gitlab.com/jtl-software/jtl-shop/plugins/jtl_test.git | Referenz-Architektur |
| Context 7 (JTL Shop 5) | https://context7.com/jtl-shop | API-Referenz |
| dbeS-Dokumentation | JTL-Wawi Handbuch | Wawi-Synchronisation |
| Bootstrap 4 Docs | https://getbootstrap.com/docs/4.6/ | Frontend-Framework |
| DataTables.js | https://datatables.net/ | jQuery-Plugin |

### Phase 1 Analysen (docs/phase1/)

1. `01_domain_objects_analysis.md` - 7 Domain Objects (RMA, Items, Reasons, etc.)
2. `02_services_analysis.md` - 4 Services (Business Logic, E-Mail, Sync)
3. `03_repositories_sql_analysis.md` - 6 Repositories, SQL-Queries
4. `04_dbes_sync_objects_analysis.md` - 5 Sync Objects (Wawi-Integration)
5. `05_template_analysis.md` - 5 Smarty Templates (UI-Struktur)
6. `06_jtl_demo_plugin_analysis.md` - Demo-Plugin Struktur (1065+ Zeilen)
7. `07_database_schema.md` - Datenbank-Design (ERD, Migrations)
8. `08_user_stories.md` - 15 User Stories (58 SP MVP)
9. `09_api_specification.md` - 19 Endpunkte (REST-API)
10. `10_plugin_architecture.md` - Plugin-Struktur (14 Ordner)
11. `11_integration_points.md` - 25+ Integrationspunkte (Events, Hooks, E-Mail)

---

## Anhang: Entscheidungslog

### Architektur-Entscheidungen

| ID | Entscheidung | Begründung | Alternativen |
|----|-------------|------------|--------------|
| AD-01 | **Keine Neuimplementierung** der RMA-Klassen | Vorhandene DomainObjects, Services, Repositories sind produktionsreif | Neuimplementierung (4+ Tage Mehraufwand) |
| AD-02 | **PSR-7 Controllers** statt Legacy-Frontend | JTL Shop 5 Best Practice, bessere Testbarkeit | Legacy-Frontend-Controller |
| AD-03 | **Event-Sourcing** für RMA-Historie | Audit-Trail, Nachvollziehbarkeit, DSGVO-konform | Einfache UPDATE-Statements |
| AD-04 | **Bidirektionale Wawi-Sync** | Status-Updates aus Wawi (z.B. "Ware eingegangen") | Unidirektional (Shop → Wawi only) |
| AD-05 | **DataTables.js** für Produktlisten | Feature-reich, Mobile-Support, Sortierung/Filter | Vanilla JS (hoher Entwicklungsaufwand) |
| AD-06 | **Migrations** statt install.sql | Versionierbarkeit, Rollback, JTL-Standard | Nur install.sql (keine Versionierung) |
| AD-07 | **Optional Login** für Retouren | Gastkunden-freundlich, niedrigere Barriere | Login-Pflicht (schlechtere UX) |
| AD-08 | **E-Mail-Templates in info.xml** | JTL-Standard, Admin kann Templates bearbeiten | Hardcoded Templates (nicht anpassbar) |

---

## Status: Phase 1 Abgeschlossen ✅

**Ergebnis:**

- ✅ **11 detaillierte Analyse-Dokumente** erstellt (3.500+ Zeilen Dokumentation)
- ✅ **5 Tabellen** vollständig designt (ERD, CREATE Statements, Indizes)
- ✅ **15 User Stories** mit 58 Story Points MVP-Scope definiert
- ✅ **19 API-Endpunkte** spezifiziert (Request/Response-Strukturen)
- ✅ **14 Plugin-Ordner** mit PSR-4-Architektur geplant
- ✅ **25+ Integrationspunkte** dokumentiert (Events, Hooks, E-Mail, Wawi-Sync)
- ✅ **Implementierungs-Timeline** erstellt (25 Arbeitstage)

**Nächster Schritt:** **Phase 2 - Plugin-Grundstruktur** (3 Tage)

---

**Dokumentversion:** 1.0  
**Letzte Aktualisierung:** 17. Oktober 2025  
**Autor:** Development Team  
**Review-Status:** ✅ Ready for Implementation
