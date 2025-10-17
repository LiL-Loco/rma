# Projektplan: JTL Shop 5 - Retouren-Plugin

## Projektziel
Entwicklung eines vollständigen Retouren-Plugins für JTL Shop 5, das Kunden ermöglicht, Retouren über Bestellnummer und E-Mail zu initiieren, Artikel auszuwählen, Retourengründe anzugeben und optional Umtauschartikel zu wählen. Die Retoure wird automatisch in der JTL-Wawi synchronisiert.

---

## Phase 1: Analyse & Konzeption (3-5 Tage)

### 1.1 Anforderungsanalyse
- **Funktionale Anforderungen dokumentieren:**
  - Retourenformular (Bestellnummer + E-Mail)
  - Artikelauswahl mit Mengenangabe
  - Retourengründe (aus vorhandenen RMA-Gründen)
  - Optional: Umtauschartikel-Auswahl
  - Bestätigungsseite mit Retourenstatus
  - E-Mail-Benachrichtigungen (Bestätigung, Retourenlabel)
  
- **Technische Anforderungen:**
  - Integration mit bestehendem RMA-System
  - dbeS-Synchronisation zur Wawi
  - Datenbankstruktur analysieren (nutzt vorhandene RMA-Tabellen)
  - Template-System (Smarty)
  - Ajax-Kommunikation

### 1.2 Architektur-Design
- Plugin-Struktur definieren
- Datenfluss designen (Shop → Wawi)
- API-Endpunkte festlegen
- Template-Hierarchie planen

---

## Phase 2: Plugin-Grundstruktur (2-3 Tage)

### 2.1 Plugin-Initialisierung
```
Struktur:
plugins/
  └── jtl_customer_returns/
      ├── version/
      │   └── 100/
      │       ├── Bootstrap.php
      │       ├── frontend/
      │       ├── adminmenu/
      │       └── sql/
      ├── info.xml
      └── Migrations/
```

### 2.2 Dateien erstellen
- **info.xml** - Plugin-Metadaten
- **Bootstrap.php** - Plugin-Hooks und Initialisierung
- **SQL-Migrations** (falls zusätzliche Tabellen nötig)
- Basis-Controller und Services

---

## Phase 3: Backend-Entwicklung (8-10 Tage)

### 3.1 Services entwickeln
#### ReturnRequestService
- `validateOrderAccess(orderNo, email)` - Bestellzugriff prüfen
- `getReturnableProducts(orderId)` - Rückgabefähige Artikel laden
- `createReturnRequest(data)` - Retoure erstellen
- `getReturnReasons()` - Retourengründe laden

#### SyncService
- `syncToWawi(rmaObject)` - Retoure zur Wawi syncen
- `createRMASyncObject()` - Sync-Objekt für dbeS erstellen

#### ExchangeService (optional)
- `getExchangeProducts(productId)` - Umtauschartikel vorschlagen
- `processExchange(returnData)` - Umtausch verarbeiten

#### NotificationService
- `sendReturnConfirmation(rmaId)` - Bestätigungs-E-Mail
- `sendReturnLabel(rmaId)` - Retourenlabel-E-Mail

### 3.2 Controller entwickeln
#### ReturnController
- `actionIndex()` - Formular-Seite
- `actionValidateOrder()` - Bestellung validieren (Ajax)
- `actionSelectProducts()` - Produktauswahl-Seite
- `actionSubmit()` - Retoure abschicken
- `actionConfirmation()` - Bestätigungsseite

### 3.3 Repository-Layer
- Erweitern der bestehenden RMA-Repositories
- `RMARepository` für Datenbankzugriffe
- `RMAItemRepository` für Retourenpositionen
- `RMAReturnAddressRepository` für Rücksendeadressen

---

## Phase 4: Frontend-Entwicklung (6-8 Tage)

### 4.1 Templates erstellen
Basierend auf Nova-Template:

#### `return_form.tpl`
```smarty
- Formular: Bestellnummer + E-Mail
- Validierung
- Submit-Button
```

#### `return_products.tpl`
```smarty
- Produkt-Übersicht aus Bestellung
- Checkboxen für Artikelauswahl
- Mengenfelder
- Retourengründe (Dropdown)
- Optional: Umtauschartikel-Auswahl
- Kommentarfeld
- Rücksendeadresse (aus Kundenkonto oder vorhandenen Lieferadressen)
```

#### `return_summary.tpl`
```smarty
- Übersicht gewählter Artikel
- Retourengründe
- Rücksendeadresse
- Bestätigen-Button
```

#### `return_confirmation.tpl`
```smarty
- Erfolgsbestätigung
- Retourennummer
- Informationen zum weiteren Ablauf
- Link zum Retourenlabel (falls verfügbar)
```

### 4.2 JavaScript/Ajax-Logik
- Formular-Validierung
- Produktauswahl (wie in `rma.tpl`)
- Mengen-Stepper
- DataTable für Produktliste
- Ajax-Requests für:
  - Bestellvalidierung
  - Produktaktualisierung
  - Retouren-Submit

### 4.3 CSS-Styling
- Responsive Design
- Nova-Template-konforme Styles
- Formularlayouts
- Produktlisten

---

## Phase 5: Wawi-Integration (5-7 Tage)

### 5.1 dbeS-Sync implementieren
- `RMASyncObject` nutzen (bereits vorhanden)
- `RMAAddressSyncObject` für Rücksendeadresse
- `RMAItemSyncObject` für Retourenpositionen
- Sync-Trigger nach Retouren-Erstellung

### 5.2 Status-Synchronisation
- Status-Mapping (Shop ↔ Wawi)
- Webhook/Callback für Wawi-Updates
- Retourenlabel-Rückmeldung

### 5.3 Fehlerbehandlung
- Logging bei Sync-Fehlern
- Retry-Mechanismus
- Admin-Benachrichtigungen

---

## Phase 6: E-Mail-System (3-4 Tage)

### 6.1 E-Mail-Templates
#### Bestätigungs-E-Mail
```
- Retourennummer
- Gewählte Artikel
- Rücksendeadresse
- Nächste Schritte
```

#### Retourenlabel-E-Mail
```
- Retourenlabel als PDF-Anhang
- Anleitung
- Versandinformationen
```

### 6.2 Template-System
- JTL-Mail-Templates nutzen
- Platzhalter definieren
- Multi-Sprachen-Support

---

## Phase 7: Admin-Bereich (4-5 Tage)

### 7.1 Plugin-Einstellungen
- Retourenzeitraum (Tage)
- Retourenlabel automatisch erstellen (Ja/Nein)
- E-Mail-Einstellungen
- Umtausch aktivieren (Ja/Nein)
- Ausgeschlossene Produktkategorien

### 7.2 Retouren-Übersicht
- Liste aller Retouren
- Filter (Status, Datum, Kunde)
- Detail-Ansicht
- Manuelle Statusänderung

### 7.3 Statistiken
- Retourenquote
- Häufigste Retourengründe
- Retourenwert

---

## Phase 8: Testing (5-7 Tage)

### 8.1 Unit-Tests
- Service-Layer testen
- Repository-Tests
- Validierungs-Tests

### 8.2 Integrationstests
- Formular-Flow komplett durchspielen
- Wawi-Sync testen
- E-Mail-Versand testen

### 8.3 Frontend-Tests
- Browser-Kompatibilität
- Responsive Design
- JavaScript-Funktionen

### 8.4 User-Acceptance-Tests
- Testkunden-Durchläufe
- Edge-Cases prüfen
- Performance-Tests

---

## Phase 9: Dokumentation (2-3 Tage)

### 9.1 Technische Dokumentation
- Code-Kommentare
- API-Dokumentation
- Datenbank-Schema

### 9.2 Benutzer-Dokumentation
- Installations-Anleitung
- Konfigurations-Handbuch
- Bedienungsanleitung für Admins
- FAQ

### 9.3 Kunden-Anleitung
- How-To für Retouren-Prozess
- Screenshots

---

## Phase 10: Deployment & Support (2-3 Tage)

### 10.1 Deployment
- Plugin-Paket erstellen
- Installations-Script
- Test-Installation auf Staging
- Produktiv-Deployment

### 10.2 Monitoring
- Fehler-Logging einrichten
- Performance-Monitoring
- Wawi-Sync überwachen

### 10.3 Support
- Support-Kanal einrichten
- Bug-Tracking-System
- Update-Planung

---

## Zeitplan Übersicht

| Phase | Dauer | Abhängigkeiten |
|-------|-------|----------------|
| 1. Analyse & Konzeption | 3-5 Tage | - |
| 2. Plugin-Grundstruktur | 2-3 Tage | Phase 1 |
| 3. Backend-Entwicklung | 8-10 Tage | Phase 2 |
| 4. Frontend-Entwicklung | 6-8 Tage | Phase 3 |
| 5. Wawi-Integration | 5-7 Tage | Phase 3 |
| 6. E-Mail-System | 3-4 Tage | Phase 3 |
| 7. Admin-Bereich | 4-5 Tage | Phase 3 |
| 8. Testing | 5-7 Tage | Phase 3-7 |
| 9. Dokumentation | 2-3 Tage | Phase 8 |
| 10. Deployment & Support | 2-3 Tage | Phase 9 |

**Gesamtdauer: 40-55 Arbeitstage (ca. 8-11 Wochen)**

---

## Technologie-Stack

### Backend
- PHP 7.4+ (JTL Shop 5 Kompatibilität)
- JTL Shop Framework
- Vorhandene RMA-Klassen nutzen:
  - `RMAService`
  - `RMARepository`
  - `RMAHistoryService`
  - `RMAReturnAddressService`

### Frontend
- Smarty Template Engine
- Bootstrap 4 (Nova-Template)
- jQuery
- DataTables.js
- Ajax für asynchrone Requests

### Datenbank
- Vorhandene Tabellen nutzen:
  - `rma`
  - `rma_items`
  - `rma_reasons`
  - `return_address`
  - `rma_history`

---

## Risiken & Mitigationen

| Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|--------|-------------------|--------|------------|
| Wawi-Sync funktioniert nicht | Mittel | Hoch | Frühzeitiges Testing, Fallback-Mechanismus |
| Vorhandenes RMA-System unvollständig | Hoch | Mittel | Code-Analyse in Phase 1, ggf. eigene Implementierung |
| Performance-Probleme bei vielen Retouren | Niedrig | Mittel | Caching, Datenbankoptimierung |
| Template-Kompatibilität | Niedrig | Niedrig | Template-Fallbacks entwickeln |

---

## Meilensteine

1. **M1: Konzept fertig** (Ende Phase 1)
2. **M2: Backend-Kern läuft** (Ende Phase 3)
3. **M3: Frontend funktionsfähig** (Ende Phase 4)
4. **M4: Wawi-Sync implementiert** (Ende Phase 5)
5. **M5: Alpha-Version** (Ende Phase 7)
6. **M6: Beta-Version** (Ende Phase 8)
7. **M7: Release** (Ende Phase 10)

---

## Nächste Schritte

1. **Projektfreigabe einholen**
2. **Entwicklungsumgebung aufsetzen**
3. **Git-Repository erstellen**
4. **Phase 1 starten: Detaillierte Anforderungsanalyse**

Möchtest du, dass ich mit der konkreten Implementierung beginne? Ich kann z.B. mit der **Plugin-Grundstruktur** (Phase 2) oder der **Service-Entwicklung** (Phase 3.1) starten.