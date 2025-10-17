# Implementierungsplan: JTL Shop 5 Retouren-Plugin

## Projektstatus: Phase 1 - Analyse & Konzeption

**Projektstart:** 17. Oktober 2025  
**Aktueller Stand:** Planung und Vorbereitung  
**Nächster Meilenstein:** M1 - Konzept fertig (in 3-5 Tagen)

---

## Ressourcen & Referenzen

### Verfügbare Referenzmaterialien
1. **jtl_rma_beispiel/** - Unfinalisierte RMA-Implementierung von JTL
   - Templates: `rma.tpl`, `rma_list.tpl`, `rma_summary.tpl`, `rma_itemlist.tpl`, `rma_table.tpl`
   - Domain-Objekte: `RMADomainObject`, `RMAItemDomainObject`, `RMAReasonDomainObject`, etc.
   - Services: `RMAService`, `RMAHistoryService`, `RMAReturnAddressService`, `RMAReasonService`
   - Repositories: `RMARepository`, `RMAItemRepository`, `RMAReasonRepository`, etc.
   - dbeS Sync-Objekte: `RMASyncObject`, `RMAItemSyncObject`, `RMAAddressSyncObject`

2. **Demo-Plugin:** https://gitlab.com/jtl-software/jtl-shop/plugins/jtl_test.git
   - Referenz für Plugin-Struktur
   - Bootstrap-Implementierung
   - Hook-System

3. **JTL Dokumentation:** https://jtl-devguide.readthedocs.io/projects/jtl-shop/de/latest/shop_plugins/index.html

---

## Phase 1: Analyse & Konzeption

**Zeitrahmen:** 3-5 Tage  
**Ziel:** Vollständiges technisches Konzept und Architektur-Design

### 1.1 Code-Analyse (Tag 1-2)

#### Aufgabe 1: RMA-Beispiel analysieren
**Ziel:** Verstehen der vorhandenen RMA-Implementierung

- [ ] **Domain-Objekte untersuchen**
  - `RMADomainObject.php` - Hauptobjekt analysieren
  - `RMAItemDomainObject.php` - Retourenpositionen verstehen
  - `RMAReasonDomainObject.php` - Retourengründe-Struktur
  - `RMAReturnAddressDomainObject.php` - Rücksendeadresse-Handling
  - Properties, Getter/Setter dokumentieren

- [ ] **Services analysieren**
  - `RMAService.php` - Kern-Funktionalität verstehen
  - `RMAHistoryService.php` - Event-Logging-Mechanismus
  - `RMAReturnAddressService.php` - Adress-Verwaltung
  - `RMAReasonService.php` - Grund-Verwaltung
  - Methoden-Signaturen dokumentieren

- [ ] **Repositories untersuchen**
  - `RMARepository.php` - CRUD-Operationen
  - `RMAItemRepository.php` - Item-Verwaltung
  - `RMAReasonRepository.php` - Gründe-Zugriff
  - SQL-Queries und Datenstruktur verstehen

- [ ] **dbeS Sync-Objekte analysieren**
  - `RMASyncObject.php` - Haupt-Sync-Struktur
  - `RMAItemSyncObject.php` - Item-Synchronisation
  - `RMAAddressSyncObject.php` - Adress-Sync
  - Sync-Flow zur Wawi verstehen

- [ ] **Templates untersuchen**
  - `rma.tpl` - Haupt-Template analysieren
  - `rma_list.tpl` - Listen-Darstellung
  - `rma_summary.tpl` - Detail-Ansicht
  - `rma_itemlist.tpl` - Item-Liste
  - JavaScript/Ajax-Implementierung verstehen
  - Smarty-Variablen dokumentieren

#### Aufgabe 2: Demo-Plugin analysieren
**Ziel:** Plugin-Struktur und Best Practices verstehen

- [ ] **Plugin-Struktur dokumentieren**
  - `info.xml` - Metadaten-Format
  - `Bootstrap.php` - Hook-Registrierung
  - Ordnerstruktur (`frontend/`, `adminmenu/`, `sql/`)
  - Versionierung (`version/100/`)

- [ ] **Hook-System verstehen**
  - Verfügbare Hooks recherchieren
  - Frontend-Hooks für Retouren-Flow
  - Admin-Hooks für Backend-Integration
  - Event-System

- [ ] **Controller-Konzept analysieren**
  - Frontend-Controller-Struktur
  - Action-Methoden
  - Request-Handling
  - Response-Rendering

### 1.2 Datenbank-Analyse (Tag 2)

#### Aufgabe 3: Existierende Tabellen dokumentieren

- [ ] **Tabelle `rma` analysieren**
  - Spalten und Datentypen dokumentieren
  - Primary/Foreign Keys
  - Indizes
  - Status-Werte (Enum/String)

- [ ] **Tabelle `rma_items` analysieren**
  - Struktur dokumentieren
  - Beziehung zu `rma` und Produkttabellen
  - Mengen- und Preis-Felder

- [ ] **Tabelle `rma_reasons` analysieren**
  - Grund-IDs und Beschreibungen
  - Mehrsprachigkeit
  - Standard-Gründe

- [ ] **Tabelle `return_address` analysieren**
  - Adressfelder
  - Beziehung zu Kunden/Bestellungen

- [ ] **Tabelle `rma_history` analysieren**
  - Event-Typen
  - Zeitstempel
  - Status-Änderungen

- [ ] **Bestelltabellen recherchieren**
  - `tbestellung` - Bestellungen
  - `tbestellpos` - Bestellpositionen
  - Verknüpfungen für Retouren-Validierung

### 1.3 Anforderungsdokumentation (Tag 3)

#### Aufgabe 4: Funktionale Requirements definieren

- [ ] **User Stories erstellen**
  - Als Kunde: Retoure einreichen
  - Als Kunde: Retourenstatus einsehen
  - Als Admin: Retouren verwalten
  - Als Admin: Statistiken einsehen

- [ ] **Prozess-Flow dokumentieren**
  ```
  Kunde → Formular → Validierung → Produktauswahl → 
  Grund-Angabe → Zusammenfassung → Submit → 
  Wawi-Sync → E-Mail-Bestätigung
  ```

- [ ] **Screen-Flows skizzieren**
  - Screen 1: Bestellung finden (Nummer + E-Mail)
  - Screen 2: Produkte auswählen
  - Screen 3: Zusammenfassung
  - Screen 4: Bestätigung

- [ ] **Edge-Cases definieren**
  - Ungültige Bestellnummer
  - E-Mail stimmt nicht überein
  - Produkt nicht retournierbar
  - Retourenfrist abgelaufen
  - Menge überschreitet Bestellung
  - Wawi-Sync fehlgeschlagen

#### Aufgabe 5: Technische Requirements definieren

- [ ] **API-Endpunkte spezifizieren**
  - `POST /returns/validate` - Bestellung validieren
  - `GET /returns/products/{orderId}` - Produkte laden
  - `POST /returns/submit` - Retoure absenden
  - `GET /returns/confirmation/{rmaId}` - Bestätigung

- [ ] **Service-Interfaces definieren**
  - `ReturnRequestService` - Methoden-Signaturen
  - `SyncService` - Sync-Methoden
  - `NotificationService` - E-Mail-Methoden
  - `ExchangeService` (optional) - Umtausch-Methoden

- [ ] **Datenvalidierung definieren**
  - Bestellnummer-Format
  - E-Mail-Validierung
  - Mengen-Limits
  - Datum-Prüfungen (Retourenfrist)

- [ ] **Sicherheitsanforderungen**
  - CSRF-Tokens
  - SQL-Injection Prevention
  - XSS-Schutz
  - Session-Handling

### 1.4 Architektur-Design (Tag 4)

#### Aufgabe 6: Plugin-Architektur entwerfen

- [ ] **Ordnerstruktur finalisieren**
  ```
  plugins/jtl_customer_returns/
  ├── info.xml
  ├── version/100/
  │   ├── Bootstrap.php
  │   ├── frontend/
  │   │   ├── ReturnController.php
  │   │   └── templates/
  │   │       ├── return_form.tpl
  │   │       ├── return_products.tpl
  │   │       ├── return_summary.tpl
  │   │       └── return_confirmation.tpl
  │   ├── adminmenu/
  │   │   ├── AdminController.php
  │   │   └── templates/
  │   │       ├── settings.tpl
  │   │       ├── list.tpl
  │   │       └── detail.tpl
  │   ├── Services/
  │   │   ├── ReturnRequestService.php
  │   │   ├── SyncService.php
  │   │   ├── ExchangeService.php
  │   │   └── NotificationService.php
  │   ├── Repositories/
  │   │   └── (Extensions falls nötig)
  │   ├── sql/
  │   │   └── migrations/
  │   └── locale/
  │       └── de_DE/
  └── Migrations/
  ```

- [ ] **Klassen-Diagramm erstellen**
  - Domain-Objekte (nutze RMA-Klassen)
  - Services mit Dependencies
  - Controller-Hierarchie
  - Repository-Layer

- [ ] **Datenfluss-Diagramm**
  - User-Input → Controller → Service → Repository → DB
  - DB → Service → Sync → Wawi
  - Service → Notification → E-Mail

- [ ] **State-Machine für Retouren**
  - Status: `pending`, `approved`, `received`, `processed`, `completed`, `rejected`
  - Übergänge definieren
  - Wer darf Status ändern (Kunde vs. Admin)

#### Aufgabe 7: Integration-Points definieren

- [ ] **JTL Shop Integration**
  - Hooks für Frontend-Rendering
  - Hooks für Admin-Menü
  - Session-Zugriff
  - Kundendaten-Zugriff

- [ ] **Wawi-Integration**
  - dbeS-Queue-Mechanismus
  - Sync-Objekt-Mapping
  - Error-Handling
  - Retry-Logik

- [ ] **E-Mail-Integration**
  - JTL-Mail-System nutzen
  - Template-Engine
  - Attachment-Handling (PDF-Label)

- [ ] **Template-Integration**
  - Nova-Template-Kompatibilität
  - Responsive-Breakpoints
  - Bootstrap-Version

### 1.5 Risiko-Analyse & Mitigation (Tag 5)

#### Aufgabe 8: Technische Risiken identifizieren

- [ ] **Wawi-Sync Risiken**
  - Risiko: Sync schlägt fehl
  - Impact: Retoure nicht in Wawi
  - Mitigation: Queue-System + Retry + Admin-Alert

- [ ] **Performance-Risiken**
  - Risiko: Viele Retouren → Langsame Queries
  - Impact: Schlechte UX
  - Mitigation: Indizes + Pagination + Caching

- [ ] **Kompatibilitäts-Risiken**
  - Risiko: RMA-System unvollständig
  - Impact: Eigene Implementierung nötig
  - Mitigation: Frühe Tests + Fallback-Code

- [ ] **Sicherheits-Risiken**
  - Risiko: Unberechtigter Zugriff auf Bestellungen
  - Impact: Datenschutz-Verstoß
  - Mitigation: E-Mail-Validierung + CSRF + Rate-Limiting

#### Aufgabe 9: Dokumentation abschließen

- [ ] **Technisches Konzept-Dokument erstellen**
  - Executive Summary
  - Architektur-Übersicht
  - API-Spezifikation
  - Datenbank-Schema
  - Deployment-Plan

- [ ] **Entscheidungs-Log**
  - Warum RMA-Klassen nutzen vs. neu bauen
  - Warum kein direkter Wawi-API-Call
  - Template-Engine-Wahl
  - JavaScript-Framework (oder plain jQuery)

- [ ] **Offene Fragen dokumentieren**
  - Retourenfrist konfigurierbar?
  - Retourenlabel automatisch oder manuell?
  - Umtausch-Feature in v1.0?
  - Multi-Sprachen in v1.0?

---

## Deliverables Phase 1

Am Ende von Phase 1 müssen folgende Dokumente vorliegen:

1. ✅ **Code-Analyse-Report**
   - RMA-Beispiel-Dokumentation
   - Demo-Plugin-Analyse
   - Wiederverwendbare Komponenten

2. ✅ **Datenbank-Dokumentation**
   - ER-Diagramm
   - Tabellen-Schemas
   - Beispiel-Daten

3. ✅ **Requirements-Dokument**
   - User Stories
   - Funktionale Requirements
   - Nicht-Funktionale Requirements
   - Edge-Cases

4. ✅ **Architektur-Dokument**
   - Klassen-Diagramm
   - Datenfluss-Diagramm
   - State-Machine
   - Integration-Points

5. ✅ **Technisches Konzept**
   - API-Spezifikation
   - Service-Interfaces
   - Template-Struktur
   - Deployment-Plan

6. ✅ **Risiko-Register**
   - Identifizierte Risiken
   - Mitigation-Strategien
   - Kontingenz-Pläne

---

## Nächste Schritte nach Phase 1

Nach Abschluss von Phase 1 und Erreichen von **Meilenstein M1**:

1. **Review-Meeting**
   - Konzept-Präsentation
   - Stakeholder-Feedback
   - Freigabe für Phase 2

2. **Phase 2 Start: Plugin-Grundstruktur**
   - `info.xml` erstellen
   - `Bootstrap.php` implementieren
   - Ordnerstruktur aufbauen
   - Git-Repository strukturieren

---

## Erfolgs-Kriterien Phase 1

Phase 1 ist erfolgreich abgeschlossen, wenn:

- [ ] Alle vorhandenen RMA-Klassen verstanden und dokumentiert
- [ ] Datenbank-Struktur vollständig analysiert
- [ ] Alle funktionalen Requirements definiert
- [ ] Architektur-Design dokumentiert und reviewed
- [ ] Technische Risiken identifiziert mit Mitigation-Plan
- [ ] Stakeholder-Freigabe erhalten
- [ ] Entwicklungsumgebung vorbereitet
- [ ] Git-Repository initialisiert

---

**Status:** 🔴 Not Started  
**Verantwortlich:** Development Team  
**Review-Datum:** 22. Oktober 2025 (in 5 Tagen)
