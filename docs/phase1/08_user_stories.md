# User Stories - Customer Returns Plugin

**Datum:** 17. Oktober 2025  
**Erstellt von:** Development Team  
**Zielgruppe:** Endkunden (Frontend), Shop-Admins (Backend)

---

## Executive Summary

Dieses Dokument definiert **15 User Stories** für das Customer Returns Plugin aus Perspektive von **Endkunden** und **Shop-Admins**. Jede Story folgt dem Format:

```
Als [Rolle]
möchte ich [Aktion]
damit [Nutzen]
```

Jede Story hat **Akzeptanzkriterien** (Testable Conditions), **Technische Details** und **Priority-Einstufung** (Must-have, Should-have, Nice-to-have).

---

## Story-Kategorien

| Kategorie | Anzahl Stories | Fokus |
|-----------|----------------|-------|
| **Retoure anlegen** | 5 | Formular, Validierung, Produktauswahl |
| **Retoure verwalten** | 3 | Status-Tracking, Historie, Umtausch |
| **Admin-Verwaltung** | 4 | Dashboard, Bearbeitung, Statistiken |
| **System-Integration** | 3 | Wawi-Sync, E-Mail, Automatisierung |

---

## 1. Endkunden-Stories (Frontend)

### Story 1.1: Retouren-Formular aufrufen

**Als** Kunde  
**möchte ich** eine Retoure über Bestellnummer und E-Mail-Adresse initiieren können  
**damit** ich ohne Login retournieren kann

#### Akzeptanzkriterien

- ✅ Formular ist über `/retoure` erreichbar (SEO-URL)
- ✅ Eingabefelder: Bestellnummer (Text), E-Mail (Email-Validierung)
- ✅ Client-seitige Validierung (jQuery):
  - Bestellnummer: Pflichtfeld, min. 5 Zeichen
  - E-Mail: Pflichtfeld, gültiges Format
- ✅ Submit-Button "Bestellung prüfen"
- ✅ CSRF-Token wird übermittelt
- ✅ Responsive Design (Mobile, Tablet, Desktop)

#### Technische Details

```php
// Controller: ReturnController::actionIndex()
// Template: return_form.tpl
// Ajax: POST /retoure/validate-order
```

**Validierungslogik:**
```php
$orderNo = Request::postVar('orderNo');
$email = Request::postVar('email');

// Query: tbestellung WHERE cBestellNr = ? AND cMail = ?
$valid = ReturnRequestService->validateOrderAccess($orderNo, $email);

if (!$valid) {
    return ['error' => 'Bestellung nicht gefunden oder E-Mail stimmt nicht überein'];
}
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 1.2: Retournierbare Produkte anzeigen

**Als** Kunde  
**möchte ich** alle retournierbaren Produkte meiner Bestellung sehen  
**damit** ich die gewünschten Artikel auswählen kann

#### Akzeptanzkriterien

- ✅ Liste zeigt nur retournierbare Produkte:
  - Versanddatum < X Tage (konfigurierbar, z.B. 14 Tage)
  - Status "Versandt" oder "Teilversandt"
  - NICHT Download-Produkte
  - NICHT mit Attribut "nicht retournierbar"
- ✅ Produktinformationen anzeigen:
  - Produktbild (Thumbnail 100×100px)
  - Produktname
  - Artikelnummer
  - Variante (z.B. "Größe: XL, Farbe: Rot")
  - Bestellte Menge
  - Bereits retournierte Menge
  - **Retournierbare Menge** (Bestellung - Bereits retourniert)
  - Einzelpreis netto
- ✅ DataTable mit Features:
  - Pagination (10 Items/Seite)
  - Sortierung (Name, Artikelnr., Preis)
  - Suchfeld (durchsucht Name + Artikelnr.)
- ✅ "Nichts retournierbar"-Meldung, wenn keine Produkte

#### Technische Details

```php
// Service: ReturnRequestService->getReturnableProducts($orderID, $langID, $cancellationTime)
// Template: return_products.tpl
// DataTables: jQuery DataTables Plugin
```

**SQL-Query-Auszug:**
```sql
SELECT 
    tartikel.cName,
    tartikel.cArtNr,
    tlieferscheinpos.fAnzahl - SUM(IFNULL(rma_items.quantity, 0)) AS returnableQty
FROM tbestellung
INNER JOIN twarenkorbpos ON ...
LEFT JOIN rma_items ON twarenkorbpos.kWarenkorbPos = rma_items.orderPosID
WHERE tbestellung.kBestellung = :orderID
  AND DATE(FROM_UNIXTIME(tversand.dErstellt)) >= DATE_SUB(NOW(), INTERVAL :days DAY)
  AND tbestellung.cStatus IN ('Versandt', 'Teilversandt')
GROUP BY tlieferscheinpos.kLieferscheinPos
HAVING returnableQty > 0
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 1.3: Artikel für Retoure auswählen

**Als** Kunde  
**möchte ich** Artikel, Menge, Retourengrund und Kommentar angeben können  
**damit** meine Retoure korrekt erfasst wird

#### Akzeptanzkriterien

- ✅ **Pro Produkt:**
  - Checkbox "Retournieren"
  - Number-Input "Menge" (min: 1, max: retournierbare Menge, step: 0.01 bei teilbaren Artikeln)
  - Dropdown "Retourengrund" (Pflichtfeld, wenn Checkbox aktiv)
    - Gründe aus `rma_reasons_lang` (lokalisiert)
    - Beispiel: "Artikel defekt", "Falsche Größe", "Gefällt nicht", "Andere"
  - Textarea "Kommentar" (optional, max. 500 Zeichen)
- ✅ **Globale Optionen:**
  - Checkbox "Versandkosten erstatten" (nur bei Vollretoure)
  - Dropdown "Rücksendeadresse" (vorausgefüllt mit Rechnungsadresse, kann geändert werden)
    - Optionen: Rechnungsadresse, Lieferadressen des Kunden
- ✅ **Validierung:**
  - Mindestens 1 Produkt ausgewählt
  - Menge > 0 und ≤ retournierbare Menge
  - Retourengrund ausgewählt
- ✅ Button "Weiter zur Zusammenfassung"

#### Technische Details

```javascript
// jQuery Validation
$('#return-form').on('submit', function(e) {
    e.preventDefault();
    
    let selectedItems = $('.product-checkbox:checked');
    if (selectedItems.length === 0) {
        alert('Bitte wählen Sie mindestens ein Produkt.');
        return false;
    }
    
    // Validate quantities
    let valid = true;
    selectedItems.each(function() {
        let $row = $(this).closest('tr');
        let qty = parseFloat($row.find('.qty-input').val());
        let maxQty = parseFloat($row.data('max-qty'));
        
        if (qty <= 0 || qty > maxQty) {
            valid = false;
        }
        
        if (!$row.find('.reason-select').val()) {
            valid = false;
        }
    });
    
    if (valid) {
        // Submit to summary page
        this.submit();
    }
});
```

**Dropdown-Optionen (Retourengründe):**
```php
$reasons = RMAReasonService->getLocalizedReasons($langID);
// [
//   ['id' => 1, 'title' => 'Artikel defekt'],
//   ['id' => 2, 'title' => 'Falsche Größe'],
//   ...
// ]
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 1.4: Retouren-Zusammenfassung prüfen

**Als** Kunde  
**möchte ich** eine Übersicht meiner Retourenwahl sehen  
**damit** ich vor Absenden nochmal prüfen kann

#### Akzeptanzkriterien

- ✅ **Anzeige der gewählten Artikel:**
  - Tabellarische Übersicht (Produktname, Menge, Grund, Kommentar, Einzelpreis, Gesamtpreis)
  - Summe: Netto, MwSt., Brutto
  - Hinweis "zzgl. Versandkosten", wenn `refundShipping = true`
- ✅ **Rücksendeadresse anzeigen:**
  - Vollständige Adresse (Name, Straße, PLZ, Ort, Land)
- ✅ **Retouren-Bedingungen:**
  - Checkbox "Ich habe die Widerrufsbelehrung gelesen" (Pflichtfeld)
  - Link zur Widerrufsbelehrung
- ✅ **Buttons:**
  - "Zurück" (zur Produktauswahl)
  - "Retoure verbindlich absenden" (Primary Button)

#### Technische Details

```php
// Controller: ReturnController::actionSummary()
// Template: return_summary.tpl
// Session-Daten: $_SESSION['return_data'] = [...]
```

**Session-Struktur:**
```php
$_SESSION['return_data'] = [
    'orderID' => 123,
    'customerID' => 456,
    'items' => [
        [
            'orderPosID' => 789,
            'productID' => 101,
            'quantity' => 2,
            'reasonID' => 1,
            'comment' => 'Falsches Modell geliefert',
            'unitPriceNet' => 29.99,
            'vat' => 19.00
        ],
        // ...
    ],
    'refundShipping' => true,
    'returnAddressID' => 456  // FK zu tkundenliefer
];
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 1.5: Retoure abschließen & Bestätigung erhalten

**Als** Kunde  
**möchte ich** nach Absenden eine Bestätigung mit Retourennummer erhalten  
**damit** ich die Retoure nachverfolgen kann

#### Akzeptanzkriterien

- ✅ **Nach Submit:**
  - RMA wird in DB gespeichert (Status: `OPEN`)
  - Eindeutige Retourennummer generiert (Format: `RMA-YYYYMMDD-###`)
  - History-Eintrag erstellt (`RMA_CREATED`)
  - E-Mail-Versand an Kunden (Bestätigungsmail)
  - Weiterleitung zu Bestätigungsseite (`/retoure/confirmation`)
- ✅ **Bestätigungsseite zeigt:**
  - ✅ Erfolgsmeldung (grüne Box)
  - ✅ Retourennummer (groß, fett)
  - ✅ Informationen zum weiteren Ablauf:
    - "Bitte senden Sie die Ware innerhalb von 14 Tagen an folgende Adresse:"
    - Rücksendeadresse des Shops (aus Config)
    - Optional: Retourenlabel (PDF-Download-Button)
  - ✅ Link "Weitere Retoure anlegen"
  - ✅ Link "Zu meinen Bestellungen" (falls eingeloggt)

#### Technische Details

```php
// Controller: ReturnController::actionSubmit()
public function actionSubmit(): void
{
    if (!Form::validateToken()) {
        throw new \Exception('CSRF-Fehler');
    }
    
    $data = $_SESSION['return_data'];
    
    // RMA erstellen
    $rma = ReturnRequestService->createReturnRequest($data);
    
    // E-Mail senden
    NotificationService->sendReturnConfirmation($rma->getId());
    
    // Session aufräumen
    unset($_SESSION['return_data']);
    
    // Redirect
    header('Location: /retoure/confirmation?rmaID=' . $rma->getId());
}
```

**E-Mail-Template-Variablen:**
```php
$smarty->assign([
    'rma_number' => 'RMA-20251017-001',
    'customer_name' => 'Max Mustermann',
    'return_items' => [...],
    'return_address' => [...],
    'order_number' => 'B-2024-12345'
]);
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 1.6: Retouren-Status verfolgen

**Als** Kunde  
**möchte ich** den Status meiner Retoure einsehen können  
**damit** ich weiß, wie es weitergeht

#### Akzeptanzkriterien

- ✅ **Zugang:**
  - Eingeloggte Kunden: "Meine Retouren"-Seite im Kundenkonto
  - Nicht eingeloggt: Link in Bestätigungs-E-Mail mit Token
- ✅ **Übersicht zeigt:**
  - Liste aller RMAs des Kunden (Tabelle)
  - Spalten: Retourennummer, Bestellnummer, Datum, Status, Aktionen
  - Status-Badge farbcodiert:
    - 🔵 OPEN: "Offen" (blau)
    - 🟡 IN_PROGRESS: "In Bearbeitung" (gelb)
    - 🟢 ACCEPTED: "Angenommen" (grün)
    - ✅ COMPLETED: "Abgeschlossen" (dunkelgrün)
    - 🔴 REJECTED: "Abgelehnt" (rot)
- ✅ **Detail-Ansicht (Klick auf Retourennummer):**
  - Retournierte Artikel (Liste)
  - Rücksendeadresse
  - Status-Historie (Timeline)
    - Zeitstempel + Event-Text
    - Beispiel: "17.10.2025 14:30 - Retoure angelegt"
  - Admin-Kommentare (falls vorhanden)

#### Technische Details

```php
// Controller: ReturnController::actionMyReturns()
// Service: RMAService->getReturnsByCustomer($customerID)
// Template: my_returns.tpl

// Detail: ReturnController::actionDetail($rmaID)
// Service: RMAHistoryService->getHistory($rmaID)
```

**Status-Historie-Query:**
```sql
SELECT eventName, eventDataJson, createDate
FROM rma_history
WHERE rmaID = :rmaID
ORDER BY createDate ASC
```

**Event-Mapping (für Timeline):**
```php
const EVENT_TEXTS = [
    'RMA_CREATED' => 'Retoure angelegt',
    'STATUS_CHANGED' => 'Status geändert zu {newStatus}',
    'ITEM_STATUS_CHANGED' => 'Artikel-Status aktualisiert',
    'SYNCED_TO_WAWI' => 'An Warenwirtschaft übertragen',
    'VOUCHER_CREATED' => 'Gutschein erstellt',
    'REFUND_PROCESSED' => 'Rückzahlung verarbeitet'
];
```

#### Priority
🟡 **Should-have** (P1)

---

### Story 1.7: Umtauschartikel wählen (Optional)

**Als** Kunde  
**möchte ich** anstelle einer Rückzahlung einen Umtauschartikel wählen können  
**damit** ich direkt ein passendes Produkt erhalte

#### Akzeptanzkriterien

- ✅ **Während Produktauswahl:**
  - Checkbox "Umtausch statt Rückzahlung" (pro Artikel)
  - Wenn aktiviert: Autocomplete-Feld "Umtauschartikel suchen"
    - Suche nach Produktname / Artikelnummer
    - Zeigt Varianten (z.B. "T-Shirt - Größe L")
  - Mengenfeld "Umtauschmenge"
- ✅ **Validierung:**
  - Umtauschartikel muss verfügbar sein (auf Lager)
  - Wenn Umtauschartikel teurer: Aufpreis anzeigen
  - Wenn günstiger: Differenz als Gutschein
- ✅ **In Zusammenfassung:**
  - Tabelle "Umtauschartikel" (getrennt von Retouren)
  - Preisdifferenz anzeigen

#### Technische Details

```php
// Service: ExchangeService->getExchangeProducts($productID)
// Autocomplete: Ajax-Endpoint /retoure/search-products

// DB-Erweiterung:
ALTER TABLE rma_items
ADD COLUMN exchangeProductID INT(11),
ADD COLUMN exchangeQuantity DECIMAL(10,2);

// Bei Submit:
if ($item['exchangeProductID']) {
    $rma->setReplacementOrderID(
        $this->createExchangeOrder($item['exchangeProductID'], $item['exchangeQuantity'])
    );
}
```

**Autocomplete-Query:**
```sql
SELECT kArtikel, cName, cArtNr, fLagerbestand
FROM tartikel
WHERE (cName LIKE :query OR cArtNr LIKE :query)
  AND fLagerbestand > 0
LIMIT 10
```

#### Priority
🟢 **Nice-to-have** (P2)

---

## 2. Admin-Stories (Backend)

### Story 2.1: Retouren-Dashboard anzeigen

**Als** Shop-Admin  
**möchte ich** eine Übersicht aller Retouren sehen  
**damit** ich den Überblick behalte

#### Akzeptanzkriterien

- ✅ **Admin-Menü:**
  - Tab "Retouren-Übersicht" im Plugin-Menü
  - Badge mit Anzahl offener RMAs (Status = OPEN)
- ✅ **Dashboard zeigt:**
  - **KPI-Kacheln:**
    - Offene Retouren (Status OPEN)
    - In Bearbeitung (Status IN_PROGRESS)
    - Heute angelegt
    - Diese Woche angelegt
  - **DataTable mit allen RMAs:**
    - Spalten: ID, Retourennummer, Kunde, Bestellnr., Datum, Status, Items-Anzahl, Wert (Brutto), Aktionen
    - Filter: Status, Datum-Range, Kunde-Suche
    - Sortierung: Standard nach Datum DESC
    - Pagination (25/Seite)
  - **Massen-Aktionen:**
    - Mehrere RMAs auswählen → Status ändern
    - CSV-Export

#### Technische Details

```php
// Controller: AdminController::renderAdminMenuTab('Retouren-Übersicht')
// Service: RMAService->getReturns($langID, $filter, $limit)
// Template: adminmenu/templates/rma_overview.tpl
```

**Filter-Optionen:**
```php
$filter = [
    'status' => Request::getInt('status', null),
    'customerID' => Request::getInt('customerID', null),
    'beforeDate' => Request::postVar('date_from'),
    'afterDate' => Request::postVar('date_to')
];
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 2.2: Retoure bearbeiten

**Als** Shop-Admin  
**möchte ich** Details einer Retoure einsehen und bearbeiten können  
**damit** ich den Prozess steuern kann

#### Akzeptanzkriterien

- ✅ **Detail-Ansicht (Klick auf RMA-ID):**
  - **Abschnitt: Allgemeine Daten**
    - Retourennummer, Bestellnummer, Kunde (Link zu Kundenkonto)
    - Erstellungsdatum, Letzte Änderung
    - Status (Dropdown, editierbar)
    - Kommentar (Textarea, Admin-only)
  - **Abschnitt: Retournierte Artikel**
    - Tabelle (Produktname, Menge, Grund, Kundenkommentar, Einzelpreis, Gesamt)
    - Pro Item: Status-Dropdown (OPEN, RECEIVED, REFUNDED, REJECTED)
    - Checkbox "Item-Status bei Speichern aktualisieren"
  - **Abschnitt: Rücksendeadresse**
    - Vollständige Adresse (readonly)
  - **Abschnitt: Erstattung**
    - Checkbox "Versandkosten erstatten"
    - Input "Gutschriftbetrag" (manuell anpassbar)
    - Button "Gutschein erstellen"
    - Button "Rückzahlung verarbeiten" (triggert Zahlungs-Gateway)
  - **Abschnitt: Historie**
    - Event-Timeline (chronologisch)
- ✅ **Aktionen:**
  - Button "Speichern" (Status + Kommentar + Item-Status)
  - Button "Wawi synchronisieren" (manueller Sync)
  - Button "E-Mail an Kunden senden" (Template-Auswahl)
  - Button "Retourenlabel drucken" (PDF-Download)

#### Technische Details

```php
// Controller: AdminController::actionEditRMA($rmaID)
// Service: RMAService->updateRMA($rmaID, $data)

// Bei Status-Änderung:
RMAHistoryService->addEvent($rmaID, 'STATUS_CHANGED', [
    'oldStatus' => $oldStatus,
    'newStatus' => $newStatus,
    'changedBy' => $adminUsername
]);

// Bei Gutschein-Erstellung:
$voucherCode = VoucherService->createVoucher($customerID, $amount);
$rma->setVoucherCredit($amount);
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 2.3: Retouren-Statistiken anzeigen

**Als** Shop-Admin  
**möchte ich** Statistiken zu Retouren sehen  
**damit** ich Trends erkenne

#### Akzeptanzkriterien

- ✅ **Admin-Tab "Statistiken":**
  - **Zeitraum-Auswahl:**
    - Dropdown: Letzte 7 Tage, Letzte 30 Tage, Letztes Quartal, Letztes Jahr, Custom
  - **Diagramme (Chart.js):**
    - Linien-Chart: RMAs pro Tag
    - Balken-Chart: RMAs nach Status
    - Kreisdiagramm: Top 10 Retourengründe
    - Balken-Chart: Top 10 retournierte Produkte
  - **Tabellen:**
    - Retourenquote pro Produktkategorie
    - Durchschnittliche Bearbeitungszeit (Status OPEN → COMPLETED)
    - Erstattungssummen (Gesamt, pro Monat)

#### Technische Details

```php
// Controller: AdminController::renderAdminMenuTab('Statistiken')
// Service: RMAService->getStatistics($dateFrom, $dateTo)
// Template: adminmenu/templates/rma_statistics.tpl
// Charts: Chart.js (CDN)
```

**Beispiel-Query (Top-Gründe):**
```sql
SELECT 
    r.id AS reasonID,
    rl.title AS reasonTitle,
    COUNT(*) AS count
FROM rma_items i
JOIN rma_reasons r ON i.reasonID = r.id
JOIN rma_reasons_lang rl ON r.id = rl.reasonID AND rl.langID = :langID
WHERE i.createDate BETWEEN :dateFrom AND :dateTo
GROUP BY r.id
ORDER BY count DESC
LIMIT 10
```

#### Priority
🟡 **Should-have** (P1)

---

### Story 2.4: Plugin konfigurieren

**Als** Shop-Admin  
**möchte ich** das Plugin konfigurieren können  
**damit** ich es an meine Shop-Bedürfnisse anpasse

#### Akzeptanzkriterien

- ✅ **Admin-Tab "Einstellungen":**
  - **Retourenbedingungen:**
    - Number-Input "Retourenfrist (Tage)" (Default: 14)
    - Checkbox "Versandkosten automatisch erstatten bei Vollretoure"
  - **Retourenlabel:**
    - Checkbox "Retourenlabel automatisch erstellen"
    - Selectbox "Versanddienstleister" (DHL, DPD, UPS, etc.)
    - Text "Label-API-Key"
  - **Produktausschlüsse:**
    - Multiselect "Ausgeschlossene Kategorien" (aus tkategorie)
    - Textarea "Ausgeschlossene Artikelnummern" (kommagetrennt)
  - **Benachrichtigungen:**
    - Text "Admin-E-Mail für Retouren-Benachrichtigungen"
    - Checkbox "E-Mail bei neuer Retoure"
  - **Umtausch (optional):**
    - Checkbox "Umtausch-Feature aktivieren"
  - **Wawi-Sync:**
    - Number "Sync-Intervall (Minuten)" (Default: 15)
    - Checkbox "Auto-Sync aktivieren"
- ✅ Button "Einstellungen speichern"

#### Technische Details

```xml
<!-- info.xml -->
<Settingslink sort="1">
    <Name>Einstellungen</Name>
    
    <Setting type="number" initialValue="14" conf="Y">
        <Name>Retourenfrist (Tage)</Name>
        <ValueName>return_period_days</ValueName>
    </Setting>
    
    <Setting type="checkbox" initialValue="on" conf="Y">
        <Name>Retourenlabel automatisch erstellen</Name>
        <ValueName>auto_create_label</ValueName>
    </Setting>
    
    <Setting type="selectbox" initialValue="dhl" conf="Y">
        <Name>Versanddienstleister</Name>
        <ValueName>shipping_provider</ValueName>
        <SelectboxOptions>
            <Option value="dhl">DHL</Option>
            <Option value="dpd">DPD</Option>
            <Option value="ups">UPS</Option>
        </SelectboxOptions>
    </Setting>
    
    <!-- ... weitere Settings -->
</Settingslink>
```

**Config-Zugriff:**
```php
$config = $this->getPlugin()->getConfig();
$returnPeriod = $config->getValue('return_period_days');
```

#### Priority
🔴 **Must-have** (P0)

---

## 3. System-Stories (Backend-Prozesse)

### Story 3.1: Automatische Wawi-Synchronisation

**Als** System  
**möchte ich** neue RMAs automatisch an die Wawi übertragen  
**damit** das Warenwirtschaftssystem aktuell bleibt

#### Akzeptanzkriterien

- ✅ **Cron-Job:**
  - Läuft alle X Minuten (konfigurierbar)
  - Holt alle RMAs mit `synced = 0`
  - Erstellt dbeS-Sync-Objekte:
    - `RMASyncObject` (Haupt-RMA)
    - `RMAItemSyncObject[]` (Items)
    - `RMAAddressSyncObject` (Rücksendeadresse)
  - Reiht Sync-Objekte in dbeS-Queue ein
  - Setzt `synced = 1` bei Erfolg
  - Erstellt History-Eintrag `SYNCED_TO_WAWI`
- ✅ **Fehlerbehandlung:**
  - Bei Sync-Fehler: 3 Retry-Versuche
  - Bei finalem Fehler: Admin-E-Mail
  - Logging in `rma_history` mit Fehlertext
- ✅ **Bidirektionale Sync:**
  - Wawi kann RMA-Status zurücksenden
  - Shop-Plugin hört auf dbeS-Updates
  - Bei Wawi-Update: Status in Shop aktualisieren + History-Event `WAWI_UPDATED`

#### Technische Details

```php
// Cron-Job: TestCronJob.php (analog zu JTL Demo)
class RMASyncCronJob extends Job
{
    public function start(QueueEntry $queueEntry): JobInterface
    {
        $rmas = RMARepository->getReturns($langID, ['synced' => 0], 100);
        
        foreach ($rmas as $rma) {
            try {
                SyncService->syncToWawi($rma);
                $rma->setSynced(1);
                RMARepository->save($rma);
            } catch (\Exception $e) {
                $this->logger->error('RMA-Sync failed: ' . $e->getMessage());
            }
        }
        
        return $this;
    }
}
```

**dbeS-Sync-Objekt-Struktur:**
```xml
<RMA>
    <kRMA>123</kRMA>
    <cRMANr>RMA-20251017-001</cRMANr>
    <kKunde>456</kKunde>
    <nStatus>0</nStatus>
    <Items>
        <Item>
            <kArtikel>789</kArtikel>
            <fMenge>2.00</fMenge>
            <kRetourengrund>1</kRetourengrund>
        </Item>
    </Items>
    <Adresse>...</Adresse>
</RMA>
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 3.2: E-Mail-Benachrichtigungen versenden

**Als** System  
**möchte ich** automatische E-Mails an Kunden versenden  
**damit** sie über den Retouren-Status informiert sind

#### Akzeptanzkriterien

- ✅ **E-Mail-Typen:**
  1. **Bestätigungs-E-Mail** (bei RMA-Erstellung):
     - Betreff: "Ihre Retoure {rmaNr} wurde registriert"
     - Inhalt: Retourennummer, gewählte Artikel, Rücksendeadresse, nächste Schritte
     - Anhang: Retourenlabel (PDF, falls aktiviert)
  2. **Status-Update-E-Mail** (bei Status-Änderung):
     - Betreff: "Ihre Retoure {rmaNr} - Status: {newStatus}"
     - Inhalt: Neuer Status, evtl. Admin-Kommentar
  3. **Gutschein-E-Mail** (bei Gutscheinerstellung):
     - Betreff: "Ihr Gutschein für Retoure {rmaNr}"
     - Inhalt: Gutscheincode, Wert, Gültigkeit
  4. **Rückzahlungs-E-Mail** (bei Rückzahlung):
     - Betreff: "Rückzahlung für Retoure {rmaNr} verarbeitet"
     - Inhalt: Betrag, Zahlungsmethode, Buchungsdatum
- ✅ **Template-System:**
  - E-Mail-Templates in `info.xml` definiert
  - Variablen: `{rma_number}`, `{customer_name}`, `{return_items}`, etc.
  - HTML + Plain-Text-Version
  - Mehrsprachig (DE/EN)

#### Technische Details

```xml
<!-- info.xml -->
<Emailtemplate>
    <Template>
        <Name>Retouren-Bestätigung</Name>
        <Description>E-Mail bei RMA-Erstellung</Description>
        <Type>text/html</Type>
        <ModulId>jtl_customer_returns_confirmation</ModulId>
        <Active>Y</Active>
        <TemplateLanguage iso="GER">
            <Subject>Ihre Retoure {$rma_number} wurde registriert</Subject>
            <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Sehr geehrte/r {$customer_name},</p>
<p>Ihre Retoure mit der Nummer <strong>{$rma_number}</strong> wurde erfolgreich angelegt.</p>
<p><strong>Retournierte Artikel:</strong></p>
<ul>
{foreach $return_items as $item}
    <li>{$item.name} - Menge: {$item.quantity}</li>
{/foreach}
</ul>
<p>Bitte senden Sie die Ware an folgende Adresse:<br>
{$return_address.street}<br>
{$return_address.postalCode} {$return_address.city}</p>
{includeMailTemplate template=footer type=html}
            ]]></ContentHtml>
        </TemplateLanguage>
    </Template>
</Emailtemplate>
```

**Service-Aufruf:**
```php
// NotificationService->sendReturnConfirmation($rmaID)
public function sendReturnConfirmation(int $rmaID): bool
{
    $rma = RMAService->getReturn($rmaID);
    $customer = CustomerService->getCustomer($rma->getCustomerID());
    
    $mail = new Mail();
    $mail->setToMail($customer->cMail);
    $mail->setToName($customer->cVorname . ' ' . $customer->cNachname);
    $mail->setSubject('Ihre Retoure ' . $rma->getRmaNr());
    
    $smarty = Shop::Container()->getSmarty();
    $smarty->assign('rma_number', $rma->getRmaNr());
    $smarty->assign('customer_name', $customer->cVorname);
    $smarty->assign('return_items', $rma->getItems());
    
    $mail->setBodyHTML($smarty->fetch('mail_templates/return_confirmation.tpl'));
    
    return $mail->send();
}
```

#### Priority
🔴 **Must-have** (P0)

---

### Story 3.3: Retourenlabel automatisch generieren

**Als** System  
**möchte ich** Retourenlabels automatisch erstellen  
**damit** Kunden die Retoure einfach zurücksenden können

#### Akzeptanzkriterien

- ✅ **Voraussetzungen:**
  - Plugin-Config: `auto_create_label = true`
  - Versanddienstleister konfiguriert (DHL, DPD, UPS)
  - API-Credentials hinterlegt
- ✅ **Generierung:**
  - Trigger: Bei RMA-Erstellung (Status = OPEN)
  - API-Call an Versanddienstleister
  - Empfängt PDF-Label (Base64 oder URL)
  - Speichert Label in `uploads/rma_labels/{rmaID}.pdf`
  - Verlinkt Label in `rma`-Tabelle (neue Spalte `labelPath`)
- ✅ **Download:**
  - Customer: Download-Link in Bestätigungs-E-Mail
  - Admin: Download-Button in RMA-Detail-Ansicht
- ✅ **Fehlerbehandlung:**
  - Bei API-Fehler: Fallback auf manuelle Label-Erstellung
  - Admin-Benachrichtigung bei Fehler

#### Technische Details

```php
// Service: ShippingLabelService->generateLabel($rmaID)
public function generateLabel(int $rmaID): ?string
{
    $rma = RMAService->getReturn($rmaID);
    $config = $this->plugin->getConfig();
    
    $provider = $config->getValue('shipping_provider');  // 'dhl', 'dpd', etc.
    
    // API-Integration (Beispiel: DHL)
    $dhlClient = new DHLClient($config->getValue('dhl_api_key'));
    
    $labelRequest = [
        'shipperAddress' => $this->getCustomerAddress($rma->getCustomerID()),
        'receiverAddress' => $this->getShopReturnAddress(),
        'weight' => 2.0,  // kg (geschätzt)
        'product' => 'DHL Retoure'
    ];
    
    $response = $dhlClient->createLabel($labelRequest);
    
    if ($response['success']) {
        $pdfPath = PFAD_ROOT . 'uploads/rma_labels/' . $rmaID . '.pdf';
        file_put_contents($pdfPath, base64_decode($response['labelData']));
        
        $rma->setLabelPath('uploads/rma_labels/' . $rmaID . '.pdf');
        RMARepository->save($rma);
        
        return $pdfPath;
    }
    
    return null;
}
```

**DB-Erweiterung:**
```sql
ALTER TABLE rma
ADD COLUMN labelPath VARCHAR(500) DEFAULT NULL COMMENT 'Pfad zum Retourenlabel-PDF';
```

#### Priority
🟡 **Should-have** (P1)

---

## 4. Story-Priorisierung

### Must-have (MVP - Phase 2)

| Story ID | Titel | Aufwand (Story Points) |
|----------|-------|------------------------|
| 1.1 | Retouren-Formular aufrufen | 3 |
| 1.2 | Retournierbare Produkte anzeigen | 8 |
| 1.3 | Artikel für Retoure auswählen | 5 |
| 1.4 | Retouren-Zusammenfassung prüfen | 3 |
| 1.5 | Retoure abschließen & Bestätigung | 5 |
| 2.1 | Retouren-Dashboard anzeigen | 5 |
| 2.2 | Retoure bearbeiten | 8 |
| 2.4 | Plugin konfigurieren | 3 |
| 3.1 | Automatische Wawi-Synchronisation | 13 |
| 3.2 | E-Mail-Benachrichtigungen | 5 |
| **TOTAL** | - | **58 Story Points** |

**Geschätzte Entwicklungszeit:** 10-12 Arbeitstage (bei 5-6 SP/Tag)

### Should-have (Phase 3)

| Story ID | Titel | Aufwand (Story Points) |
|----------|-------|------------------------|
| 1.6 | Retouren-Status verfolgen | 5 |
| 2.3 | Retouren-Statistiken anzeigen | 8 |
| 3.3 | Retourenlabel automatisch generieren | 13 |
| **TOTAL** | - | **26 Story Points** |

**Geschätzte Entwicklungszeit:** 5-6 Arbeitstage

### Nice-to-have (Phase 4)

| Story ID | Titel | Aufwand (Story Points) |
|----------|-------|------------------------|
| 1.7 | Umtauschartikel wählen | 13 |

---

## 5. User-Journey-Diagramme

### Journey 1: Kunde erstellt Retoure (Happy Path)

```
┌─────────────────┐
│ 1. Formular     │  → Eingabe: Bestellnr. + E-Mail
│ /retoure        │  → Validation: Ajax-Call
└────────┬────────┘
         │ ✅ Valid
         ▼
┌─────────────────┐
│ 2. Produktliste │  → Anzeige: Retournierbare Produkte
│ return_products │  → Auswahl: Checkboxen + Mengen + Gründe
└────────┬────────┘
         │ Submit
         ▼
┌─────────────────┐
│ 3. Zusammenfas. │  → Anzeige: Gewählte Items + Adresse
│ return_summary  │  → Validierung: Checkbox "AGB gelesen"
└────────┬────────┘
         │ Submit
         ▼
┌─────────────────┐
│ 4. Processing   │  → DB: RMA erstellen
│ actionSubmit()  │  → E-Mail: Bestätigung senden
│                 │  → Wawi: Sync-Queue einreihen
└────────┬────────┘
         │ Redirect
         ▼
┌─────────────────┐
│ 5. Bestätigung  │  → Anzeige: RMA-Nummer + Anleitung
│ confirmation    │  → Download: Retourenlabel (optional)
└─────────────────┘
```

**Durchschnittliche Dauer:** 3-5 Minuten

### Journey 2: Admin bearbeitet Retoure

```
┌─────────────────┐
│ 1. Dashboard    │  → Anzeige: Alle RMAs (DataTable)
│ rma_overview    │  → Filter: Status = OPEN
└────────┬────────┘
         │ Klick auf RMA-ID
         ▼
┌─────────────────┐
│ 2. Detail-View  │  → Anzeige: Alle RMA-Daten
│ rma_detail      │  → Edit: Status → IN_PROGRESS
│                 │  → Edit: Kommentar "Wird geprüft"
└────────┬────────┘
         │ Speichern
         ▼
┌─────────────────┐
│ 3. Processing   │  → DB: RMA aktualisieren
│ actionSaveRMA() │  → History: Event "STATUS_CHANGED"
│                 │  → E-Mail: Status-Update an Kunde
└────────┬────────┘
         │ Redirect
         ▼
┌─────────────────┐
│ 4. Dashboard    │  → Anzeige: Updated RMA in Liste
│ rma_overview    │  → Badge: "In Bearbeitung" (gelb)
└─────────────────┘
```

**Durchschnittliche Dauer:** 2-3 Minuten/RMA

---

## 6. Akzeptanz-Test-Szenarien

### Szenario 1: Vollständige Retoure mit Rückzahlung

**Given:** Kunde hat Bestellung B-2024-12345 mit 3 Artikeln erhalten  
**When:** Kunde retourniert alle 3 Artikel mit Grund "Gefällt nicht"  
**Then:**
- ✅ RMA wird mit Status OPEN erstellt
- ✅ Retourennummer RMA-YYYYMMDD-XXX generiert
- ✅ E-Mail mit Bestätigung versendet
- ✅ RMA in Wawi synchronisiert
- ✅ Admin sieht RMA in Dashboard
- ✅ Kunde sieht RMA in "Meine Retouren"

### Szenario 2: Teilretoure mit Versandkostenerstattung

**Given:** Bestellung mit 5 Artikeln (Versandkosten: 4,90 €)  
**When:** Kunde retourniert 2 von 5 Artikeln + wählt "Versandkosten erstatten"  
**Then:**
- ✅ RMA enthält nur 2 Items
- ✅ `refundShipping = 1` in DB
- ✅ Erstattungssumme = 2 Items + 4,90 €
- ✅ In Zusammenfassung: "inkl. Versandkosten" angezeigt

### Szenario 3: Retoure außerhalb Retourenfrist

**Given:** Plugin-Config: `return_period_days = 14`  
**And:** Bestellung wurde vor 20 Tagen versandt  
**When:** Kunde versucht Retoure  
**Then:**
- ✅ Produktliste zeigt: "Keine retournierbaren Produkte"
- ✅ Info-Text: "Retourenfrist von 14 Tagen abgelaufen"

### Szenario 4: Admin lehnt Retoure ab

**Given:** RMA im Status IN_PROGRESS  
**When:** Admin ändert Status auf REJECTED + Kommentar "Ware beschädigt retourniert"  
**Then:**
- ✅ Status in DB = REJECTED
- ✅ History-Event: STATUS_CHANGED mit Kommentar
- ✅ E-Mail an Kunde: "Ihre Retoure wurde abgelehnt" + Grund

### Szenario 5: Wawi aktualisiert RMA-Status

**Given:** RMA wurde mit Wawi synchronisiert (`synced = 1`, `wawiID = 1001`)  
**When:** Wawi sendet dbeS-Update: Status → COMPLETED  
**Then:**
- ✅ Shop-Plugin empfängt Update
- ✅ RMA-Status in Shop = COMPLETED
- ✅ History-Event: WAWI_UPDATED
- ✅ E-Mail an Kunde: "Rückzahlung verarbeitet"

---

## Zusammenfassung & Nächste Schritte

### ✅ Dokumentiert

- [x] 15 User Stories (7 Customer, 4 Admin, 4 System)
- [x] Akzeptanzkriterien für jede Story
- [x] Technische Details (Controller, Services, Templates, SQL)
- [x] Priorisierung (Must/Should/Nice-to-have)
- [x] Story Points & Aufwandsschätzung
- [x] User Journeys (Diagramme)
- [x] 5 Akzeptanz-Test-Szenarien

### 📋 Wichtige Erkenntnisse

1. **MVP (Must-have): 58 Story Points** → 10-12 Arbeitstage
2. **Kern-Features:** Formular, Produktauswahl, Admin-Dashboard, Wawi-Sync, E-Mail
3. **Kritische Pfade:** Retouren-Erstllung (1.1-1.5), Wawi-Sync (3.1)
4. **Optional:** Umtausch (1.7), Retourenlabel (3.3), Statistiken (2.3)

### ⏭️ Nächster Schritt: Todo 9

**API-Spezifikation erstellen** - Controller-Actions, Request/Response-Strukturen, Routing

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** 15 User Stories mit vollständigen Akzeptanzkriterien definiert. **MVP-Scope** auf 58 Story Points geschätzt. **User Journeys** visualisiert. **Test-Szenarien** dokumentiert.
