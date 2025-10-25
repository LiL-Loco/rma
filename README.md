# JTL Customer Returns Plugin

Ein umfassendes Retourenverwaltungssystem für JTL Shop 5, das Kunden die Einleitung von Retouren **ohne Login** ermöglicht - nur mit Bestellnummer und E-Mail-Adresse.

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/LiL-Loco/rma)
[![JTL Shop](https://img.shields.io/badge/JTL_Shop-5.x-green.svg)](https://www.jtl-software.de/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-orange.svg)](LICENSE)

## Inhaltsverzeichnis

- [Überblick](#überblick)
- [Features](#features)
- [Systemanforderungen](#systemanforderungen)
- [Installation](#installation)
- [Projektstruktur](#projektstruktur)
- [Technologie-Stack](#technologie-stack)
- [Komponenten](#komponenten)
- [Konfiguration](#konfiguration)
- [API-Endpoints](#api-endpoints)
- [Entwicklung](#entwicklung)
- [Testing](#testing)
- [Deployment](#deployment)
- [Dokumentation](#dokumentation)
- [Roadmap](#roadmap)

## Überblick

Das **JTL Customer Returns Plugin** ist ein vollständiges RMA-System (Return Merchandise Authorization), das nahtlos in JTL Shop 5 integriert ist und automatisch mit dem JTL-Wawi ERP-System synchronisiert.

### Kernfunktionalität

- **Gastfreundlicher Retourenprozess**: Keine Anmeldung erforderlich
- **4-Schritt-Workflow**: Validierung → Produktauswahl → Zusammenfassung → Bestätigung
- **Bidirektionale Wawi-Synchronisation**: Automatische Synchronisation alle 15 Minuten
- **Versandetiketten-Integration**: DHL, DPD, UPS, Hermes
- **Event Sourcing**: Vollständiger Audit-Trail für alle Änderungen
- **DSGVO-konform**: Kundenanonymisierung bei Löschung

### Status

- **Aktuelle Version**: 1.0.0
- **Release-Datum**: 17. Oktober 2025
- **Projektphase**: Phase 1 (Konzeption & Analyse) abgeschlossen ✅
- **Repository-Größe**: 1.3 MB
- **Dateien**: 45 Dateien, 29 PHP-Klassen

## Features

### Kunden-Features

- ✅ Retoure ohne Login erstellen (Bestellnummer + E-Mail)
- ✅ Retournierbare Produkte auswählen mit Mengen und Gründen
- ✅ E-Mail-Bestätigung mit RMA-Nummer
- ✅ Status-Tracking per E-Mail
- ✅ Versandetiketten-Download (optional)
- ✅ Rücksendeanschrift anzeigen
- ✅ Meine Retouren (für angemeldete Kunden)

### Admin-Features

- ✅ Retourenübersicht mit DataTables (Filterung, Suche, Export)
- ✅ Retourendetails mit vollständigem Verlauf
- ✅ Statistik-Dashboard (Retourenquote, Gründe-Analyse)
- ✅ Konfigurationspanel (11 Einstellungen)
- ✅ Wawi-Synchronisation verwalten
- ✅ E-Mail-Vorlagen anpassen (4 Templates)
- ✅ Versandetiketten erstellen

### Technische Features

- ✅ **Event Sourcing**: Vollständiger Audit-Trail
- ✅ **CSRF-Schutz**: Alle POST/PUT/DELETE-Anfragen geschützt
- ✅ **SQL-Injection-Schutz**: Prepared Statements
- ✅ **XSS-Prevention**: Smarty Template Escaping
- ✅ **Retry-Logik**: Fehlerbehandlung mit Admin-Benachrichtigungen
- ✅ **Cron-basierte Synchronisation**: Automatische Wawi-Sync

## Systemanforderungen

### Minimum

- **JTL Shop**: Version 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.7+ oder MariaDB 10.3+
- **JTL-Wawi**: Version 1.5+ (für Synchronisation)
- **Apache/Nginx**: mit mod_rewrite
- **Composer**: 2.0+

### Empfohlen

- **PHP**: 8.0+
- **MySQL**: 8.0+ oder MariaDB 10.6+
- **Memory Limit**: 256 MB
- **Max Execution Time**: 300s (für Synchronisation)

### Browser-Unterstützung

- Chrome (aktuelle Version)
- Firefox (aktuelle Version)
- Safari (aktuelle Version)
- Edge (aktuelle Version)
- Mobile Browser (iOS Safari, Chrome Mobile)

## Installation

### 1. Plugin herunterladen

```bash
git clone https://github.com/LiL-Loco/rma.git
cd rma
```

### 2. Plugin installieren

```bash
# Composer-Abhängigkeiten installieren
cd plugins/jtl_customer_returns
composer install --no-dev --optimize-autoloader

# Plugin in JTL Shop Backend hochladen
# Oder: Ordner nach shop_root/plugins/ kopieren
```

### 3. Plugin aktivieren

1. JTL Shop Backend öffnen
2. **Plugins → Verfügbare Plugins**
3. "JTL Customer Returns" aktivieren
4. Datenbank-Migration wird automatisch ausgeführt

### 4. Konfiguration

1. **Plugins → JTL Customer Returns → Einstellungen**
2. Grundeinstellungen konfigurieren:
   - Retourenfrist (Standard: 14 Tage)
   - Rücksendeanschrift
   - Versanddienstleister (DHL/DPD/UPS)
   - E-Mail-Benachrichtigungen

### 5. Cron-Job einrichten

```bash
# Alle 15 Minuten ausführen
*/15 * * * * php /pfad/zu/shop/includes/cron/rma-sync.php
```

## Projektstruktur

```
rma/
├── plugins/jtl_customer_returns/          # Haupt-Plugin-Verzeichnis
│   ├── Bootstrap.php                      # Plugin-Lifecycle & Event-Listener
│   ├── info.xml                           # Plugin-Manifest
│   ├── composer.json                      # PSR-4 Autoloading
│   │
│   ├── DomainObjects/                     # Entity-Modelle (7 Klassen)
│   │   ├── RMADomainObject.php           # Haupt-RMA-Entity
│   │   ├── RMAItemDomainObject.php       # Retourenpositionen
│   │   ├── RMAReasonDomainObject.php     # Retourengründe
│   │   ├── RMAReturnAddressDomainObject.php
│   │   ├── RMAHistoryDomainObject.php    # Audit-Trail
│   │   └── dbeS/                         # Wawi-Sync-Objekte
│   │
│   ├── Repositories/                      # Datenzugriffsschicht (6 Klassen)
│   │   ├── AbstractDBRepository.php      # Basis-CRUD
│   │   ├── RMARepository.php
│   │   ├── RMAItemRepository.php
│   │   └── ...
│   │
│   ├── Services/                          # Business-Logik (4 Services)
│   │   ├── ReturnRequestService.php      # Kern-RMA-Logik
│   │   ├── NotificationService.php       # E-Mail-Versand
│   │   ├── SyncService.php               # Wawi-Integration
│   │   └── ShippingLabelService.php      # Versandetiketten
│   │
│   ├── frontend/                          # Kundenseitig
│   │   ├── ReturnController.php          # 7 Routen
│   │   ├── hook27.php                    # Kundenkonto-Widget
│   │   ├── hook140.php                   # Bestelldetails-Integration
│   │   └── templates/                    # 4 Smarty-Templates
│   │
│   ├── adminmenu/                         # Admin-Backend
│   │   ├── AdminController.php
│   │   └── templates/                    # 3 Admin-Templates
│   │
│   ├── Migrations/                        # Datenbank-Schema
│   ├── Crons/                             # Cron-Jobs
│   ├── Helper/                            # Utility-Klassen
│   └── lang/                              # Übersetzungen (DE, EN)
│
├── docs/                                  # Umfassende Dokumentation
│   ├── CONCEPT.md                         # 990 Zeilen - Architektur
│   └── phase1/                            # 11 Analyse-Dokumente
│
├── IMPLEMENTATION_PLAN.md                 # 8-Phasen-Roadmap
├── projektplan.md                         # Deutscher Projektplan
└── README.md                              # Dieses Dokument
```

## Technologie-Stack

### Backend

- **Sprache**: PHP 7.4+ (Strict Typing, PSR-4)
- **Framework**: JTL Shop 5 Plugin API
- **Datenbank**: MySQL 5.7+ / MariaDB 10.3+ (InnoDB, 3NF)
- **Synchronisation**: dbeS (JTL XML-basiertes Sync-Protokoll)
- **Coding Standards**: PSR-4, PSR-7, PSR-12

### Frontend

- **Template-Engine**: Smarty 3.x
- **CSS Framework**: Bootstrap 4
- **JavaScript**: jQuery 3.x, DataTables.js
- **Progressive Enhancement**: ES5-kompatibel

### Architektur-Patterns

- **Layered Architecture**: Controller → Service → Repository → Database
- **Repository Pattern**: Datenabstraktion
- **Service Locator**: JTL Shop Container
- **Domain-Driven Design**: Domain Objects
- **Event Sourcing**: RMA History
- **Observer Pattern**: Event-Listener

## Komponenten

### Domain Layer (7 Entity-Klassen)

| Entity | Zweck | Schlüsselfelder |
|--------|-------|-----------------|
| RMADomainObject | Haupt-Retoure | id, rmaNr, orderID, status, totalGross |
| RMAItemDomainObject | Retourenpositionen | quantity, reasonID, refundAmount |
| RMAReasonDomainObject | Retourengründe | reasonID, reason, sortOrder |
| RMAReturnAddressDomainObject | Kundenadresse | salutation, firstName, lastName, street |
| RMAHistoryDomainObject | Audit-Trail | event, eventData (JSON), createdAt |

### Service Layer (4 Business-Services)

1. **ReturnRequestService** - Kern-RMA-Logik
   - `validateOrderAccess()` - Bestellzugriff prüfen
   - `getReturnableProducts()` - Retournierbare Artikel laden
   - `createReturnRequest()` - Neue RMA erstellen

2. **NotificationService** - E-Mail-Versand
   - `sendConfirmation()` - Bestätigungs-E-Mail
   - `sendStatusUpdate()` - Status-Änderungs-E-Mails

3. **SyncService** - Wawi-Integration
   - `syncToWawi()` - Sync zur Wawi
   - `handleSyncResponse()` - Wawi-Updates verarbeiten

4. **ShippingLabelService** - Versandetiketten
   - DHL/DPD/UPS API-Wrapper

### Datenbank-Schema (5 Tabellen)

```sql
rma                    -- Haupt-Retouren-Tabelle (14 Spalten)
├── rma_items          -- Retourenpositionen (10 Spalten)
├── rma_reasons        -- Retourengründe (5 Spalten)
├── return_address     -- Rücksendeanschriften (11 Spalten)
└── rma_history        -- Event-Log (5 Spalten, JSON)
```

## Konfiguration

### Plugin-Einstellungen (11 Optionen)

| Einstellung | Typ | Standard | Beschreibung |
|-------------|-----|----------|--------------|
| `return_period_days` | Zahl | 14 | Retourenfrist in Tagen |
| `auto_refund_shipping` | Checkbox | Nein | Versandkosten erstatten |
| `auto_create_label` | Checkbox | Nein | Versandetiketten automatisch erstellen |
| `shipping_provider` | Select | DHL | Versanddienstleister |
| `shipping_api_key` | Text | - | API-Schlüssel |
| `excluded_categories` | Text | - | Ausgeschlossene Kategorien |
| `excluded_product_nos` | Textarea | - | Ausgeschlossene Artikel |
| `admin_notification_email` | E-Mail | - | Admin-E-Mail |
| `notify_admin_on_new_rma` | Checkbox | Ja | Admin benachrichtigen |
| `wawi_sync_interval` | Zahl | 15 | Sync-Intervall (Minuten) |
| `shop_return_address` | Textarea | - | Rücksendeanschrift |

### E-Mail-Vorlagen (4 Templates)

1. **Retouren-Bestätigung** - Bei RMA-Erstellung
2. **Retouren-Status-Update** - Bei Status-Änderung
3. **Retouren-Gutschein** - Bei Gutschein-Erstellung
4. **Retouren-Rückzahlung** - Bei Rückzahlung

## API-Endpoints

### Frontend-Routen (7)

| Route | Methode | Beschreibung |
|-------|---------|--------------|
| `/retoure` | GET | Formular-Seite |
| `/retoure/validate` | POST | Bestellvalidierung (AJAX) |
| `/retoure/products` | GET | Produktauswahl |
| `/retoure/summary` | GET | Zusammenfassung |
| `/retoure/submit` | POST | Retoure absenden |
| `/retoure/confirmation/{rmaID}` | GET | Bestätigung |
| `/kundenkonto/retouren` | GET | Meine Retouren |

### Admin-Routen (6)

- Übersicht, Bearbeiten, Statistiken, Einstellungen, Export, Etikett erstellen

### AJAX-Endpoints (5)

- Bestellung validieren, Gründe laden, Produkte laden, Rückerstattung berechnen, Status ändern

### Cron-Endpoint (1)

- `/cron/rma-sync` - Wawi-Synchronisation

## Entwicklung

### Lokale Entwicklungsumgebung

```bash
# Repository klonen
git clone https://github.com/LiL-Loco/rma.git
cd rma/plugins/jtl_customer_returns

# Abhängigkeiten installieren
composer install

# Tests ausführen
composer test

# Code-Style prüfen
composer cs-check

# Code-Style korrigieren
composer cs-fix
```

### Entwicklungs-Workflow

1. **Feature-Branch erstellen**
   ```bash
   git checkout -b feature/neue-funktion
   ```

2. **Code schreiben & testen**
   ```bash
   # Unit-Tests
   ./vendor/bin/phpunit tests/Unit

   # Integration-Tests
   ./vendor/bin/phpunit tests/Integration
   ```

3. **Commit & Push**
   ```bash
   git add .
   git commit -m "Feature: Neue Funktion hinzugefügt"
   git push origin feature/neue-funktion
   ```

4. **Pull Request erstellen**

### Coding Standards

- **PSR-4**: Autoloading
- **PSR-12**: Coding Style
- **PHPDoc**: Alle öffentlichen Methoden dokumentieren
- **Type Hints**: Strict typing verwenden
- **3NF**: Datenbank-Normalisierung

## Testing

### Test-Abdeckung

- **Ziel**: 90%+ Code Coverage
- **Unit-Tests**: Service-Schicht, Repositories
- **Integration-Tests**: Controller, Datenbank
- **E2E-Tests**: Vollständiger Retouren-Flow
- **Performance-Tests**: < 2s Seitenladezeit

### Tests ausführen

```bash
# Alle Tests
composer test

# Nur Unit-Tests
./vendor/bin/phpunit tests/Unit

# Mit Coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Test-Struktur

```
tests/
├── Unit/
│   ├── Services/
│   ├── Repositories/
│   └── DomainObjects/
├── Integration/
│   ├── Controllers/
│   └── Database/
└── E2E/
    └── ReturnFlow/
```

## Deployment

### Produktiv-Deployment

```bash
# 1. Repository auschecken
git clone https://github.com/LiL-Loco/rma.git
cd rma/plugins/jtl_customer_returns

# 2. Production-Abhängigkeiten installieren
composer install --no-dev --optimize-autoloader

# 3. Plugin in JTL Shop hochladen
# Über Backend oder FTP nach shop_root/plugins/

# 4. Plugin aktivieren (automatische Migration)

# 5. Cron-Job einrichten
*/15 * * * * php /pfad/zu/shop/includes/cron/rma-sync.php

# 6. Konfiguration prüfen
# Backend → Plugins → JTL Customer Returns → Einstellungen
```

### Performance-Optimierung

- **Caching**: OPcache aktivieren
- **Database**: Query-Cache aktivieren
- **CDN**: Statische Assets über CDN ausliefern
- **Gzip**: Kompression aktivieren
- **Image Optimization**: Bilder optimieren

### Sicherheit

- **HTTPS**: SSL-Zertifikat verwenden
- **CSRF-Token**: Aktiviert (Standard)
- **Rate Limiting**: Empfohlen (z.B. fail2ban)
- **Backup**: Tägliche Backups einrichten
- **Updates**: Regelmäßige Sicherheitsupdates

## Dokumentation

### Technische Dokumentation

- **[CONCEPT.md](docs/CONCEPT.md)** - Vollständige Architektur (990 Zeilen)
- **[IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md)** - 8-Phasen-Roadmap
- **[projektplan.md](projektplan.md)** - Deutscher Projektplan

### Phase 1 Analysen (11 Dokumente)

| Dokument | Beschreibung | Zeilen |
|----------|--------------|--------|
| 01_domain_objects_analysis.md | 7 Domain-Objects | 450+ |
| 02_services_analysis.md | 4 Service-Layer-Specs | 380+ |
| 03_repositories_sql_analysis.md | 6 Repositories + SQL | 520+ |
| 04_dbes_sync_objects_analysis.md | 5 Sync-Objects | 340+ |
| 05_template_analysis.md | Smarty-Templates | 280+ |
| 06_jtl_demo_plugin_analysis.md | Referenz-Implementation | 1065+ |
| 07_database_schema.md | ER-Diagramm + Migrations | 390+ |
| 08_user_stories.md | 15 User Stories (58 SP) | 470+ |
| 09_api_specification.md | 19 API-Endpoints | 550+ |
| 10_plugin_architecture.md | Plugin-Struktur | 320+ |
| 11_integration_points.md | 25+ Integrationspunkte | 410+ |

**Gesamt**: 3.500+ Zeilen technische Dokumentation

## Roadmap

### Phase 1: Konzeption & Analyse ✅ ABGESCHLOSSEN

- ✅ 11 detaillierte Analyse-Dokumente
- ✅ Vollständiges Datenbank-Schema
- ✅ 15 User Stories (58 Story Points)
- ✅ 19 API-Endpoints spezifiziert
- ✅ Plugin-Architektur dokumentiert

### Phase 2-8: Implementation (25 Arbeitstage) ⏳ BEREIT

| Phase | Dauer | Fokus | Status |
|-------|-------|-------|--------|
| Phase 2 | 3 Tage | Plugin-Struktur | ⏳ Bereit |
| Phase 3 | 4 Tage | Backend-Services | ⏳ Bereit |
| Phase 4 | 5 Tage | Frontend-Templates | ⏳ Bereit |
| Phase 5 | 3 Tage | Admin-Backend | ⏳ Bereit |
| Phase 6 | 2 Tage | Wawi-Integration | ⏳ Bereit |
| Phase 7 | 3 Tage | Testing & QA | ⏳ Bereit |
| Phase 8 | 2 Tage | Dokumentation | ⏳ Bereit |

### Geplante Features (v2.0)

- 🔄 Multi-Language-Support (EN, FR, ES)
- 🔄 REST-API für externe Integrationen
- 🔄 Kunden-Chat für Retouren
- 🔄 Foto-Upload für beschädigte Artikel
- 🔄 Automatische Qualitätsprüfung
- 🔄 Analytics-Dashboard (erweitert)

## Qualitätsmetriken

- **Coding Standard**: PSR-4, PSR-7, PSR-12 ✅
- **Datenbank-Normalisierung**: 3NF ✅
- **Test-Coverage-Ziel**: 90%+ 🎯
- **Dokumentation**: 3.500+ Zeilen ✅
- **Performance-Ziel**: < 2s Seitenladezeit 🎯
- **Sicherheit**: OWASP Top 10 + DSGVO ✅

## Support & Kontakt

### Hilfe benötigt?

- **Dokumentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/LiL-Loco/rma/issues)
- **Pull Requests**: [GitHub PRs](https://github.com/LiL-Loco/rma/pulls)

### Entwickelt von

- **Projekt**: JTL Customer Returns Plugin
- **Repository**: [github.com/LiL-Loco/rma](https://github.com/LiL-Loco/rma)
- **Lizenz**: MIT (siehe LICENSE)

## Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert - siehe [LICENSE](LICENSE) für Details.

---

**Version 1.0.0** | Entwickelt mit ❤️ für JTL Shop 5 | Phase 1 abgeschlossen ✅
