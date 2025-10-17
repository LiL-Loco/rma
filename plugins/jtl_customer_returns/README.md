# Customer Returns Plugin für JTL Shop 5

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/LiL-Loco/rma)
[![JTL Shop](https://img.shields.io/badge/JTL_Shop-5.0+-green.svg)](https://www.jtl-software.de/)
[![License](https://img.shields.io/badge/license-MIT-orange.svg)](LICENSE.txt)

Vollständiges **Retouren-Management-System** für JTL Shop 5, das Kunden ermöglicht, Retouren **ohne Login** über Bestellnummer und E-Mail zu initiieren.

---

## 🚀 Features

### Kundensicht
- ✅ **Gastfreundlicher Retouren-Flow** - Keine Registrierung erforderlich
- ✅ **Bestellvalidierung** via Bestellnummer + E-Mail
- ✅ **Produktauswahl** mit Mengen und Retourengründen
- ✅ **E-Mail-Bestätigung** mit Retourennummer
- ✅ **Retourenlabel-Download** (optional, DHL/DPD/UPS)
- ✅ **Status-Tracking** per E-Mail

### Admin-Backend
- ✅ **Retouren-Übersicht** mit Filter/Suche (DataTables)
- ✅ **Retouren-Detail** mit Status-Verwaltung
- ✅ **Statistiken** (KPIs, Charts)
- ✅ **Config-Optionen** (Retourenfrist, Versanddienstleister, etc.)

### Wawi-Integration
- ✅ **Bidirektionale Synchronisation** Shop ↔ JTL-Wawi (dbeS)
- ✅ **Automatischer Cron-Job** (alle 15 Min., konfigurierbar)
- ✅ **Fehlerbehandlung** mit Admin-Benachrichtigung

---

## 📋 Anforderungen

- **JTL Shop:** >= 5.0.0
- **PHP:** >= 7.4
- **MySQL/MariaDB:** >= 5.7 / 10.3
- **Extensions:** `ext-json`, `ext-pdo`

---

## 📦 Installation

### 1. Plugin herunterladen

```bash
cd /pfad/zu/jtl-shop/plugins/
git clone https://github.com/LiL-Loco/rma.git jtl_customer_returns
```

**Oder** ZIP-Download:
```bash
cd /pfad/zu/jtl-shop/plugins/
wget https://github.com/LiL-Loco/rma/archive/main.zip
unzip main.zip -d jtl_customer_returns
```

### 2. Composer-Dependencies installieren

```bash
cd jtl_customer_returns
composer install --no-dev
```

### 3. Plugin im JTL Shop Backend installieren

1. JTL Shop Admin öffnen: `https://ihr-shop.de/admin`
2. Navigation: **Plugins > Plugin-Manager**
3. Plugin **"Customer Returns"** suchen
4. Button **"Installieren"** klicken
5. Plugin **aktivieren**

### 4. Konfiguration

Nach der Installation unter **Plugins > Customer Returns > Einstellungen** konfigurieren:

| Setting | Empfehlung |
|---------|------------|
| **Retourenfrist** | 14 Tage |
| **Versandkosten erstatten** | Ja |
| **Retourenlabel automatisch** | Nein (bis API-Key konfiguriert) |
| **Admin-E-Mail** | Ihre E-Mail-Adresse |
| **Wawi-Sync** | Ja (Intervall: 15 Min.) |

---

## 🔧 Konfiguration

### Versanddienstleister-API

Für automatische Retourenlabel-Erstellung benötigen Sie einen API-Key:

**DHL:**
```
1. DHL-Geschäftskundenportal: https://geschaeftskunden.dhl.de
2. API-Zugangsdaten anfordern
3. API-Key in Plugin-Einstellungen eintragen
```

**DPD/UPS:** Analog zum DHL-Prozess

### Rücksendeadresse

Unter **Einstellungen > Rücksendeadresse** die Adresse eintragen:

```
Beispiel-Shop GmbH
Retouren-Abteilung
Musterstraße 123
12345 Musterstadt
Deutschland
```

### E-Mail-Templates anpassen

1. Navigation: **E-Mails > E-Mail-Vorlagen**
2. Vorlagen filtern nach **"Retouren"**
3. Templates anpassen:
   - `Retouren-Bestätigung`
   - `Retouren-Status-Update`
   - `Retouren-Gutschein`
   - `Retouren-Rückzahlung`

---

## 📊 Datenbank-Schema

Das Plugin erstellt **5 Tabellen**:

| Tabelle | Zweck | Zeilen (ca.) |
|---------|-------|--------------|
| `rma` | Haupt-Retourentabelle | 14 Spalten |
| `rma_items` | Retourenpositionen | 10 Spalten |
| `rma_reasons` | Retourengründe (mehrsprachig) | 12 Default-Gründe |
| `return_address` | Rücksendeadressen | 11 Spalten |
| `rma_history` | Event-Sourcing Log | 5 Spalten |

**Migrations:** Automatisch beim Plugin-Install ausgeführt

---

## 🛠️ Entwicklung

### Lokale Entwicklungsumgebung

```bash
# Repository klonen
git clone https://github.com/LiL-Loco/rma.git
cd rma

# Dev-Dependencies installieren
composer install

# Tests ausführen
./vendor/bin/phpunit

# Code-Style prüfen
./vendor/bin/phpcs --standard=PSR12 version/100/
```

### Ordner-Struktur

```
plugins/jtl_customer_returns/
├── version/100/
│   ├── Bootstrap.php               # Plugin-Lifecycle
│   ├── Migrations/                 # Datenbank-Migrations
│   ├── DomainObjects/              # Entities (7 Klassen)
│   ├── Repositories/               # Data Access (6 Klassen)
│   ├── Services/                   # Business Logic (4 Services)
│   ├── frontend/                   # Frontend-Controller + Templates
│   ├── adminmenu/                  # Admin-Backend
│   └── Crons/                      # Wawi-Sync-Job
├── info.xml                        # Plugin-Manifest
├── composer.json                   # Dependencies
└── README.md                       # Diese Datei
```

### Testing

```bash
# Unit-Tests
./vendor/bin/phpunit --coverage-html coverage/

# Integration-Tests
./vendor/bin/phpunit --testsuite=integration

# E2E-Tests (Browser)
npm run test:e2e
```

---

## 📖 Dokumentation

Vollständige Dokumentation in `/docs`:

- [CONCEPT.md](../../docs/CONCEPT.md) - Vollständiges Konzeptdokument
- [Phase 1 Analysen](../../docs/phase1/) - Detaillierte Analysen (11 Dokumente)
  - Domain Objects
  - Services
  - Repositories & SQL
  - dbeS Sync Objects
  - Templates
  - Database Schema
  - User Stories
  - API Specification
  - Plugin Architecture
  - Integration Points

---

## 🤝 Contributing

Beiträge willkommen! Bitte:

1. **Fork** des Repositories
2. **Feature-Branch** erstellen: `git checkout -b feature/neue-funktion`
3. **Commit** mit aussagekräftiger Message: `git commit -m "Add: Neue Funktion XY"`
4. **Push** zum Branch: `git push origin feature/neue-funktion`
5. **Pull Request** erstellen

### Code-Standards

- **PSR-12** Coding Standard
- **PHPDoc** für alle Public-Methoden
- **Type Hints** für Parameter und Return-Types
- **90%+ Test Coverage** für neue Features

---

## 🐛 Troubleshooting

### Plugin lässt sich nicht installieren

**Fehlermeldung:** `Migration failed`

**Lösung:**
```bash
# Datenbank-Log prüfen
tail -f /var/log/mysql/error.log

# Manual Migration ausführen
mysql -u root -p shopdb < plugins/jtl_customer_returns/version/100/sql/install.sql
```

### Wawi-Sync funktioniert nicht

**Prüfen:**
1. Cron-Job aktiv? **Admin > System > Cron-Jobs**
2. dbeS-Queue läuft? **Admin > System > dbeS-Abgleich**
3. Fehler im Log? **Admin > System > Systemlog**

**Debug-Modus:**
```php
// In Bootstrap.php temporär aktivieren
$this->getLogger()->setLevel(\Psr\Log\LogLevel::DEBUG);
```

### E-Mails werden nicht versendet

**Prüfen:**
1. SMTP-Einstellungen: **Admin > E-Mails > SMTP-Einstellungen**
2. E-Mail-Queue: **Admin > E-Mails > E-Mail-Warteschlange**
3. Test-E-Mail senden: **Admin > E-Mails > Test-E-Mail**

---

## 📄 Lizenz

[MIT License](LICENSE.txt)

Copyright (c) 2025 Development Team

---

## 🙋 Support

- **Issues:** https://github.com/LiL-Loco/rma/issues
- **Discussions:** https://github.com/LiL-Loco/rma/discussions
- **E-Mail:** dev@example.com

---

## 🗓️ Roadmap

### Version 1.1 (Q1 2026)
- [ ] Umtausch-Funktion (Artikel austauschen statt erstatten)
- [ ] Gutschein-Erstellung (automatisch bei Retoure)
- [ ] PDF-Retourenlabel-Generierung (ohne externe API)

### Version 1.2 (Q2 2026)
- [ ] Multi-Shop-Support
- [ ] Advanced Statistics (Charts, Dashboards)
- [ ] Retouren-Gründe-Analyse (häufigste Gründe)

### Version 2.0 (Q3 2026)
- [ ] JTL Shop 6 Kompatibilität
- [ ] GraphQL-API
- [ ] Mobile App Integration

---

**Status:** ✅ Phase 1 abgeschlossen, Phase 2 in Entwicklung

**Last Update:** 17. Oktober 2025
