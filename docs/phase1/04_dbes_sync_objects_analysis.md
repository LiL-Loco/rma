# dbeS Sync-Objekte - Wawi-Synchronisation

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** `jtl_rma_beispiel/RMA/DomainObjects/dbeS/`

---

## Übersicht: dbeS-System

**dbeS** = **D**aten**b**ank**e**xport/**S**ystem  
→ JTL-internes Synchronisations-Framework für Wawi ↔ Shop Datenabgleich

### Funktionsprinzip

```
┌─────────────┐          ┌─────────────┐          ┌─────────────┐
│  JTL-Wawi   │          │    dbeS     │          │  JTL-Shop   │
│  (Desktop)  │◄────────►│   Queue     │◄────────►│   (Web)     │
└─────────────┘          └─────────────┘          └─────────────┘
      │                         │                         │
      │  1. Erstelle Retoure    │                         │
      ├────────────────────────►│                         │
      │                         │  2. Sync-Objekt         │
      │                         ├────────────────────────►│
      │                         │  3. Import in DB        │
      │                         │◄────────────────────────┤
      │  4. Bestätigung         │                         │
      │◄────────────────────────┤                         │
```

### Architektur

Alle Sync-Objekte erben von **`AbstractDbeSObject`** (JTL Core-Klasse):

```php
abstract class AbstractDbeSObject {
    public function __construct() {
        // XML-Mapping-Logik
        // Property-Validation
    }
}
```

**Eigenschaften:**
- ✅ **Type-Safe** - PHP 8.1 Constructor Property Promotion
- ✅ **Immutable** - Public readonly Properties (nach Konstruktion)
- ✅ **Self-Documenting** - DocBlocks mit Property-Typen
- ✅ **Nested Objects** - Child-Objekte (Items, Adresse)

---

## 1. RMASyncObject (Haupt-Sync-Objekt)

**Zweck:** Container für komplette Retoure aus Wawi → Shop

### Properties

| Property | Typ | Wawi-Name | Beschreibung |
|----------|-----|-----------|--------------|
| `item` | `RMAItemSyncObject[]` | `<item>` | Array aller Retourenpositionen |
| `adresse` | `RMAAddressSyncObject` | `<adresse>` | Rücksendeadresse |
| `kRMRetoure` | `int` | `<kRMRetoure>` | Wawi-Retouren-ID |
| `cRetoureNr` | `string` | `<cRetoureNr>` | Retourennummer (z.B. "RMA-2025-00123") |
| `cKommentarExtern` | `string` | `<cKommentarExtern>` | Kundenkommentar |
| `nHerkunft` | `int` | `<nHerkunft>` | Herkunft (1=Wawi, 2=Shop) |
| `cShopID` | `int` | `<cShopID>` | Shop-Identifikator |
| `nKuponGutschriftGutschreiben` | `bool` | `<nKuponGutschriftGutschreiben>` | Gutschrift als Gutschein? |
| `kKundeShop` | `int` | `<kKundeShop>` | Shop-Kunden-ID |
| `nVersandkostenErstatten` | `bool` | `<nVersandkostenErstatten>` | Versandkosten erstatten? |
| `dErstellt` | `string` | `<dErstellt>` | Erstelldatum (ISO 8601) |

### Objektstruktur

```php
RMASyncObject {
    +kRMRetoure: 12345
    +cRetoureNr: "RMA-2025-00123"
    +cKommentarExtern: "Artikel defekt"
    +nHerkunft: 1
    +cShopID: 1
    +nKuponGutschriftGutschreiben: true
    +kKundeShop: 678
    +nVersandkostenErstatten: false
    +dErstellt: "2025-10-17 14:30:00"
    
    +adresse: RMAAddressSyncObject {
        +cFirma: "Musterfirma GmbH"
        +cVorname: "Max"
        +cName: "Mustermann"
        ...
    }
    
    +item: [
        RMAItemSyncObject {
            +kArtikel: 1001
            +cName: "T-Shirt Blau L"
            +fAnzahl: 1.0
            ...
        },
        RMAItemSyncObject {
            +kArtikel: 1002
            +cName: "Hose Schwarz 32"
            +fAnzahl: 2.0
            ...
        }
    ]
}
```

### Mapping zu DB-Tabellen

**Wawi → Shop Transformation:**

```php
// In dbeS Import-Handler (JTL Core)
$rmaSync = new RMASyncObject(...);

// Mapped zu:
INSERT INTO rma (
    wawiID,              // ← $rmaSync->kRMRetoure
    rmaNr,               // ← $rmaSync->cRetoureNr
    customerID,          // ← $rmaSync->kKundeShop
    voucherCredit,       // ← $rmaSync->nKuponGutschriftGutschreiben
    refundShipping,      // ← $rmaSync->nVersandkostenErstatten
    comment,             // ← $rmaSync->cKommentarExtern
    createDate,          // ← $rmaSync->dErstellt
    synced,              // ← 1 (automatisch)
    status               // ← 1 (OPEN)
) VALUES (...)
```

---

## 2. RMAItemSyncObject (Positionsdaten)

**Zweck:** Einzelne Retouren-Position (Artikel)

### Properties

| Property | Typ | Wawi-Name | Beschreibung |
|----------|-----|-----------|--------------|
| `kArtikel` | `int` | `<kArtikel>` | Artikel-ID (FK zu `tartikel`) |
| `kRMGrund` | `int` | `<kRMGrund>` | Retourengrund-ID (FK zu `rma_reasons`) |
| `cName` | `string` | `<cName>` | Produktname |
| `fAnzahl` | `float` | `<fAnzahl>` | Menge (Dezimal für teilbare Artikel) |
| `kLieferscheinPos` | `int` | `<kLieferscheinPos>` | Lieferschein-Position-ID |
| `nGutschreiben` | `bool` | `<nGutschreiben>` | Position gutschreiben? |
| `dErstellt` | `string` | `<dErstellt>` | Erstelldatum |

### Mapping zu DB-Tabelle

```php
INSERT INTO rma_items (
    rmaID,               // ← Parent RMA ID
    productID,           // ← $item->kArtikel
    reasonID,            // ← $item->kRMGrund
    name,                // ← $item->cName
    quantity,            // ← $item->fAnzahl
    shippingNotePosID,   // ← $item->kLieferscheinPos
    createDate           // ← $item->dErstellt
) VALUES (...)
```

### Wichtige Felder

**`nGutschreiben` (Gutschreiben):**
- `true` → Automatisch gutschreiben (z.B. bei Mängel)
- `false` → Manuelle Prüfung erforderlich

**`kLieferscheinPos` (Lieferscheinposition):**
- Verknüpfung zur ursprünglichen Versandposition
- Ermöglicht Rückverfolgung: Retoure → Lieferung → Bestellung

---

## 3. RMAAddressSyncObject (Rücksendeadresse)

**Zweck:** Adresse für Rücksendung der retournierten Ware

### Properties

| Property | Typ | Wawi-Name | Beschreibung |
|----------|-----|-----------|--------------|
| `cFirma` | `string` | `<cFirma>` | Firmenname |
| `cZusatz` | `string` | `<cZusatz>` | Firmenzusatz |
| `cAnrede` | `string` | `<cAnrede>` | Anrede (m/w/d) |
| `cTitel` | `string` | `<cTitel>` | Akademischer Titel |
| `cVorname` | `string` | `<cVorname>` | Vorname |
| `cName` | `string` | `<cName>` | Nachname |
| `cStrasse` | `string` | `<cStrasse>` | Straße + Hausnummer |
| `cAdressZusatz` | `string` | `<cAdressZusatz>` | Adresszusatz |
| `cPLZ` | `string` | `<cPLZ>` | Postleitzahl |
| `cOrt` | `string` | `<cOrt>` | Stadt |
| `cLand` | `string` | `<cLand>` | Ländername |
| `cTel` | `string` | `<cTel>` | Telefon |
| `cMobil` | `string` | `<cMobil>` | Mobiltelefon |
| `cMail` | `string` | `<cMail>` | E-Mail |
| `cFax` | `string` | `<cFax>` | Fax |
| `cBundesland` | `string` | `<cBundesland>` | Bundesland/Region |
| `cISO` | `string` | `<cISO>` | Länder-ISO-Code (DE, AT, CH) |

### Mapping zu DB-Tabelle

```php
INSERT INTO return_address (
    id,                  // ← generiert via RMAReturnAddressRepository->generateID()
    rmaID,               // ← Parent RMA ID
    customerID,          // ← RMASyncObject->kKundeShop
    salutation,          // ← $adresse->cAnrede
    firstName,           // ← $adresse->cVorname
    lastName,            // ← $adresse->cName
    academicTitle,       // ← $adresse->cTitel
    companyName,         // ← $adresse->cFirma
    companyAdditional,   // ← $adresse->cZusatz
    street,              // ← parse($adresse->cStrasse) → Straße
    houseNumber,         // ← parse($adresse->cStrasse) → Hausnummer
    addressAdditional,   // ← $adresse->cAdressZusatz
    postalCode,          // ← $adresse->cPLZ
    city,                // ← $adresse->cOrt
    state,               // ← $adresse->cBundesland
    countryISO,          // ← $adresse->cISO
    phone,               // ← $adresse->cTel
    mobilePhone,         // ← $adresse->cMobil
    fax,                 // ← $adresse->cFax
    mail                 // ← $adresse->cMail
) VALUES (...)
```

### Besonderheiten

⚠️ **`cStrasse` Parsing erforderlich:**
```php
// Wawi: "Musterstraße 42"
// Shop: street="Musterstraße", houseNumber="42"

preg_match('/^(.+?)\s+(\d+.*)$/', $adresse->cStrasse, $matches);
$street = $matches[1] ?? $adresse->cStrasse;
$houseNumber = $matches[2] ?? '';
```

✅ **Vollständige Kontaktdaten:**
- Telefon, Mobil, Fax, E-Mail
- Ermöglicht Kontaktaufnahme bei Rückfragen

---

## 4. RMAReasonSyncObject (Retourengründe)

**Zweck:** Synchronisation von Retourengründen aus Wawi

### Properties

| Property | Typ | Wawi-Name | Beschreibung |
|----------|-----|-----------|--------------|
| `wawiID` | `int` | `<wawiID>` | Wawi-Grund-ID (UNIQUE) |
| `productTypeGroupID` | `int` | `<productTypeGroupID>` | Produkttyp-Gruppen-ID |
| `localization` | `RMAReasonLocalizationSyncObject[]` | `<localization>` | Übersetzungen |

### Mapping zu DB-Tabelle

```php
INSERT INTO rma_reasons (
    id,                  // ← Auto-Increment (Shop)
    wawiID,              // ← $reason->wawiID (UNIQUE)
    productTypeGroupID   // ← $reason->productTypeGroupID
) VALUES (...)

// Für jede Lokalisierung:
foreach ($reason->localization as $loc) {
    INSERT INTO rma_reasons_lang (
        reasonID,        // ← Neue Shop-Grund-ID
        langID,          // ← $loc->langID
        title            // ← $loc->title
    ) VALUES (...)
}
```

### Use Cases

**Produkttyp-spezifische Gründe:**
```php
// Elektronik (productTypeGroupID = 1)
- "Defekt"
- "Garantiefall"
- "DOA (Dead on Arrival)"

// Kleidung (productTypeGroupID = 2)
- "Falsche Größe"
- "Falsche Farbe"
- "Passt nicht"

// Allgemein (productTypeGroupID = 0)
- "Gefällt nicht"
- "Falsche Bestellung"
- "Anderer Grund"
```

---

## 5. RMAReasonLocalizationSyncObject (Grund-Übersetzungen)

**Zweck:** Multi-Language Support für Retourengründe

### Properties

| Property | Typ | Wawi-Name | Beschreibung |
|----------|-----|-----------|--------------|
| `reasonID` | `int` | `<reasonID>` | FK zu `rma_reasons.wawiID` |
| `langID` | `int` | `<langID>` | Sprach-ID (1=DE, 2=EN, etc.) |
| `title` | `string` | `<title>` | Lokalisierter Text |

### Mapping zu DB-Tabelle

```php
INSERT INTO rma_reasons_lang (
    id,                  // ← Auto-Increment
    reasonID,            // ← Shop-Grund-ID (nicht Wawi-ID!)
    langID,              // ← $loc->langID
    title                // ← $loc->title
) VALUES (...)
```

### Beispiel Multi-Language

```php
RMAReasonSyncObject {
    +wawiID: 5
    +productTypeGroupID: 0
    +localization: [
        RMAReasonLocalizationSyncObject {
            +reasonID: 5
            +langID: 1      // Deutsch
            +title: "Falsche Größe"
        },
        RMAReasonLocalizationSyncObject {
            +reasonID: 5
            +langID: 2      // Englisch
            +title: "Wrong size"
        },
        RMAReasonLocalizationSyncObject {
            +reasonID: 5
            +langID: 3      // Französisch
            +title: "Mauvaise taille"
        }
    ]
}
```

---

## Sync-Workflow: Wawi → Shop

### 1. Retoure in Wawi erstellen

**Administrator-Aktion:**
```
1. Bestellung in Wawi öffnen
2. "Retoure erstellen" klicken
3. Artikel auswählen + Mengen festlegen
4. Retourengrund wählen
5. Rücksendeadresse eingeben
6. Speichern → dbeS-Export triggern
```

### 2. XML-Generierung (Wawi)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dbeS>
    <abgleich>
        <rmretoure>
            <kRMRetoure>12345</kRMRetoure>
            <cRetoureNr>RMA-2025-00123</cRetoureNr>
            <kKundeShop>678</kKundeShop>
            <cKommentarExtern>Artikel defekt</cKommentarExtern>
            <nHerkunft>1</nHerkunft>
            <cShopID>1</cShopID>
            <nKuponGutschriftGutschreiben>1</nKuponGutschriftGutschreiben>
            <nVersandkostenErstatten>0</nVersandkostenErstatten>
            <dErstellt>2025-10-17 14:30:00</dErstellt>
            
            <adresse>
                <cFirma></cFirma>
                <cVorname>Max</cVorname>
                <cName>Mustermann</cName>
                <cStrasse>Musterstraße 42</cStrasse>
                <cPLZ>12345</cPLZ>
                <cOrt>Berlin</cOrt>
                <cISO>DE</cISO>
                ...
            </adresse>
            
            <item>
                <kArtikel>1001</kArtikel>
                <kRMGrund>5</kRMGrund>
                <cName>T-Shirt Blau L</cName>
                <fAnzahl>1</fAnzahl>
                <kLieferscheinPos>9876</kLieferscheinPos>
                <nGutschreiben>1</nGutschreiben>
                <dErstellt>2025-10-17 14:30:00</dErstellt>
            </item>
        </rmretoure>
    </abgleich>
</dbeS>
```

### 3. dbeS-Import (Shop)

**JTL Core-Handler:**
```php
// includes/src/dbeS/Handler/RMAHandler.php (hypothetisch)
class RMAHandler extends AbstractHandler {
    public function handle(RMASyncObject $rmaSync): void {
        // 1. Validierung
        $customer = $this->validateCustomer($rmaSync->kKundeShop);
        
        // 2. RMA-Haupteintrag erstellen
        $rmaRepo = new RMARepository();
        $rma = new RMADomainObject();
        $rma->setWawiID($rmaSync->kRMRetoure);
        $rma->setRmaNr($rmaSync->cRetoureNr);
        $rma->setCustomerID($rmaSync->kKundeShop);
        $rma->setComment($rmaSync->cKommentarExtern);
        $rma->setSynced(1); // Bereits synchronisiert!
        $rma->setStatus(1); // OPEN
        
        $rmaId = $rmaRepo->insert($rma);
        
        // 3. Rücksendeadresse
        $addressRepo = new RMAReturnAddressRepository();
        $address = $this->mapAddress($rmaSync->adresse, $rmaId);
        $addressRepo->insert($address);
        
        // 4. Items
        $itemRepo = new RMAItemRepository();
        foreach ($rmaSync->item as $itemSync) {
            $item = $this->mapItem($itemSync, $rmaId);
            $itemRepo->insert($item);
        }
        
        // 5. History-Eintrag
        $historyService = new RMAHistoryService();
        $historyService->logEvent($rmaId, 'CREATED_FROM_WAWI', [
            'wawiID' => $rmaSync->kRMRetoure
        ]);
        
        // 6. E-Mail an Kunden
        $notificationService = new NotificationService();
        $notificationService->sendReturnConfirmation($rmaId);
    }
}
```

### 4. Bestätigung Wawi ← Shop

**Response XML:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<dbeS>
    <response>
        <rmretoure>
            <kRMRetoure>12345</kRMRetoure>
            <status>success</status>
            <shopRmaID>456</shopRmaID>
        </rmretoure>
    </response>
</dbeS>
```

---

## Reverse-Sync: Shop → Wawi

**Szenario:** Kunde erstellt Retoure im Shop → Sync zu Wawi

### 1. Retoure im Shop erstellen

```php
// Frontend: ReturnController->actionSubmit()
$returnService = new ReturnRequestService();
$rma = $returnService->createReturnRequest([
    'orderID' => 123,
    'customerID' => 678,
    'items' => [...],
    'address' => [...]
]);

// Status: synced = 0 (noch nicht synchronisiert)
```

### 2. Sync-Queue eintragen

```php
$syncService = new SyncService();
$syncObject = $syncService->createRMASyncObject($rma);

// In dbeS-Queue
$queue = Shop::Container()->getDB()->insert('tdbesexportqueue', [
    'cType' => 'rmretoure',
    'kKey' => $rma->getID(),
    'nPrio' => 100,
    'dErstellt' => date('Y-m-d H:i:s')
]);
```

### 3. dbeS-Export-Cronjob

```php
// Läuft alle X Minuten
$queueItems = $this->getQueueItems('rmretoure');

foreach ($queueItems as $item) {
    $rma = $rmaRepo->getByID($item->kKey);
    $syncObject = $syncService->createRMASyncObject($rma);
    
    // XML generieren
    $xml = $this->generateXML($syncObject);
    
    // An Wawi senden
    $this->sendToWawi($xml);
    
    // Als synced markieren
    $rmaRepo->markedAsSynced([$rma]);
}
```

### 4. Wawi-Import

**JTL-Wawi:**
```
1. dbeS-Abgleich starten
2. Shop-XML empfangen
3. Retoure in Wawi-DB importieren
4. Retourennummer generieren (falls leer)
5. Bestätigung an Shop
```

---

## Wichtige Erkenntnisse

### ✅ Vollständig bidirektional

- **Wawi → Shop:** Retouren aus Wawi erscheinen im Shop
- **Shop → Wawi:** Kundenretouren werden in Wawi importiert

### ✅ Type-Safe Design

```php
// Compiletime-Fehler bei falschen Typen
$rmaSync = new RMASyncObject(
    item: [new RMAItemSyncObject(...)],  // Array von RMAItemSyncObject
    adresse: new RMAAddressSyncObject(...), // Kein Array!
    kRMRetoure: 123,                      // Int, kein String!
    ...
);
```

### ✅ Nested Objects

```php
// Hierarchie:
RMASyncObject
├── RMAItemSyncObject[] (1:n)
└── RMAAddressSyncObject (1:1)

RMAReasonSyncObject
└── RMAReasonLocalizationSyncObject[] (1:n)
```

### ⚠️ Adress-Parsing erforderlich

**Wawi:** `cStrasse = "Musterstraße 42"`  
**Shop:** `street = "Musterstraße"`, `houseNumber = "42"`

→ Regex-Parsing notwendig

### ⚠️ ID-Mapping

**Wawi-ID ≠ Shop-ID:**
```php
// Wawi: kRMRetoure = 12345
// Shop: rma.id = 456, rma.wawiID = 12345
```

→ Immer `wawiID` für Rückreferenzen nutzen!

### 🔄 Sync-Status

```php
// rma.synced
0 = Nur im Shop (noch nicht in Wawi)
1 = Mit Wawi synchronisiert
```

---

## Verwendung im Customer Returns Plugin

### ✅ Wiederverwendbar

Alle Sync-Objekte sind **vollständig nutzbar** für:

1. **Import von Wawi-Retouren** (falls Administrator Retoure manuell erstellt)
2. **Export von Shop-Retouren** nach Kundenretoure

### 🔧 Integration in SyncService

```php
namespace Plugin\jtl_customer_returns\Services;

use JTL\RMA\Services\RMAService;
use JTL\RMA\DomainObjects\dbeS\RMASyncObject;
use JTL\RMA\DomainObjects\dbeS\RMAItemSyncObject;
use JTL\RMA\DomainObjects\dbeS\RMAAddressSyncObject;

class SyncService {
    public function createRMASyncObject(RMADomainObject $rma): RMASyncObject {
        // Items sammeln
        $itemSyncObjects = [];
        foreach ($rma->getItems() as $item) {
            $itemSyncObjects[] = new RMAItemSyncObject(
                kArtikel: $item->getProductID(),
                kRMGrund: $item->getReasonID(),
                cName: $item->getName(),
                fAnzahl: $item->getQuantity(),
                kLieferscheinPos: $item->getShippingNotePosID(),
                nGutschreiben: true, // Auto-gutschreiben
                dErstellt: $item->getCreateDate()
            );
        }
        
        // Adresse
        $address = $rma->getReturnAddress();
        $addressSync = new RMAAddressSyncObject(
            cFirma: $address->getCompanyName(),
            cZusatz: $address->getCompanyAdditional(),
            cAnrede: $address->getSalutation(),
            cTitel: $address->getAcademicTitle(),
            cVorname: $address->getFirstName(),
            cName: $address->getLastName(),
            cStrasse: $address->getStreet() . ' ' . $address->getHouseNumber(),
            cAdressZusatz: $address->getAddressAdditional(),
            cPLZ: $address->getPostalCode(),
            cOrt: $address->getCity(),
            cLand: '', // Wird via ISO ermittelt
            cTel: $address->getPhone(),
            cMobil: $address->getMobilePhone(),
            cMail: $address->getMail(),
            cFax: $address->getFax(),
            cBundesland: $address->getState(),
            cISO: $address->getCountryISO()
        );
        
        // Haupt-Objekt
        return new RMASyncObject(
            item: $itemSyncObjects,
            adresse: $addressSync,
            kRMRetoure: $rma->getWawiID() ?? 0,
            cRetoureNr: $rma->getRmaNr(),
            cKommentarExtern: $rma->getComment(),
            nHerkunft: 2, // 2 = Shop-Ursprung
            cShopID: 1,
            nKuponGutschriftGutschreiben: $rma->getVoucherCredit(),
            kKundeShop: $rma->getCustomerID(),
            nVersandkostenErstatten: $rma->getRefundShipping(),
            dErstellt: $rma->getCreateDate()
        );
    }
    
    public function syncToWawi(RMADomainObject $rma): bool {
        try {
            $syncObject = $this->createRMASyncObject($rma);
            
            // In Queue eintragen
            Shop::Container()->getDB()->insert('tdbesexportqueue', [
                'cType' => 'rmretoure',
                'kKey' => $rma->getID(),
                'nPrio' => 100
            ]);
            
            return true;
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error(
                'RMA Sync failed: ' . $e->getMessage()
            );
            return false;
        }
    }
}
```

---

## Nächste Schritte

1. ✅ **Sync-Objekte verstanden**
2. ⬜ **Templates analysieren** - Frontend-UI
3. ⬜ **Helper-Klassen** - `RMAItems`, Events
4. ⬜ **Demo-Plugin** - Plugin-Struktur verstehen

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** dbeS-Synchronisation vollständig dokumentiert. **Bidirektionaler Sync** (Wawi ↔ Shop) verstanden. Sync-Objekte **wiederverwendbar** für Customer Returns Plugin.
