# Smarty Templates - Frontend-UI Analyse

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** `jtl_rma_beispiel/*.tpl`

---

## Übersicht

Die Templates implementieren ein **2-Schritt-Retourenprozess** mit DataTables, Ajax-Updates und Bootstrap 4 Komponenten.

### Template-Architektur

```
rma.tpl (Haupttemplate)
├── rma_table.tpl (Produktauswahl-Tabelle)
├── rma_itemlist.tpl (Sticky-Sidebar mit gewählten Items)
└── rma_summary.tpl (Bestätigungsseite vor Submit)

rma_list.tpl (Retouren-Übersicht für Kunde)
```

### UI-Flow

```
┌──────────────────┐
│  1. Schritt:     │
│  Produktauswahl  │
│  (rma.tpl)       │
└────────┬─────────┘
         │
         ├─► Artikel wählen
         ├─► Mengen eingeben
         ├─► Gründe auswählen
         ├─► Rücksendeadresse
         │
         ▼
┌──────────────────┐
│  2. Schritt:     │
│  Zusammenfassung │
│  (rma_summary)   │
└────────┬─────────┘
         │
         ├─► Übersicht prüfen
         ├─► "Retoure erstellen"
         │
         ▼
┌──────────────────┐
│  Bestätigung +   │
│  Retouren-Liste  │
│  (rma_list.tpl)  │
└──────────────────┘
```

---

## 1. rma.tpl (Haupt-Template: Produktauswahl)

**Zweck:** 2-Spalten-Layout mit Produkttabelle + Sticky-Sidebar

### Smarty-Blöcke

| Block-Name | Zweck | Überschreibbar |
|------------|-------|----------------|
| `account-rma` | Haupt-Container | ✅ |
| `account-rma-card` | Linke Spalte (Produktliste) | ✅ |
| `account-rma-card-header` | Card-Header mit "Alle auswählen" | ✅ |
| `account-rma-card-body` | Card-Body mit DataTable | ✅ |
| `account-rma-itemlist-form` | Formular Rücksendeadresse | ✅ |
| `account-rma-summary` | Zusammenfassung (Schritt 2) | ✅ |
| `account-rma-return-address-modal` | Modal: Neue Adresse | ✅ |
| `account-rma-script` | JavaScript-Logik | ✅ |

### HTML-Struktur

```smarty
{row class="rma-step-1"}
    {* Linke Spalte: Produkttabelle *}
    {col cols=12 md=7 lg=8 class='rma-form-wrapper'}
        {card}
            {cardheader}
                <span class="h3">{lang key='addItems' section='rma'}</span>
                <a href="#" class="select_all">Alle auswählen</a>
            {/cardheader}
            {cardbody}
                {* Filter: Bestellnummer *}
                {select name="orders"}
                    {foreach $returnableOrders as $order}
                        <option value="{$order['orderNo']}">{$order['orderNo']}</option>
                    {/foreach}
                {/select}
                
                {* Produkttabelle einbinden *}
                {include file='account/rma_table.tpl'}
            {/cardbody}
        {/card}
    {/col}
    
    {* Rechte Spalte: Sticky-Sidebar *}
    {col cols=12 md=5 lg=4 class='rma-items-wrapper'}
        {card class="sticky-card"}
            {* Gewählte Items *}
            {include file='account/rma_itemlist.tpl'}
            
            {* Formular: Rücksendeadresse *}
            {form id='rma'}
                {select name="returnAddress"}
                    {include file='account/returnaddress/form_option.tpl'}
                {/select}
                {button type="submit"}{lang key='continueOrder'}{/button}
            {/form}
        {/card}
    {/col}
{/row}

{* Schritt 2: Zusammenfassung (initial versteckt) *}
{row class="rma-step-2 d-none"}
    <div id="rma-summary"></div>
{/row}
```

### Smarty-Variablen

| Variable | Typ | Quelle | Beschreibung |
|----------|-----|--------|--------------|
| `$returnableProducts` | `RMAItemDomainObject[]` | Controller | Retournierbare Artikel |
| `$returnableOrders` | `array` | Controller | Bestellnummern-Filter |
| `$rma` | `RMADomainObject` | Service | RMA-Objekt (Draft) |
| `$rmaService` | `RMAService` | Service | Service-Instanz |
| `$shippingAddresses` | `array` | Customer | Lieferadressen |
| `$shippingCountries` | `array` | Config | Länder-Dropdown |
| `$orderNo` | `string` | GET-Param | Vorauswahl Bestellung |

### JavaScript-Logik (Inline)

#### DataTable-Initialisierung

```javascript
function initDataTable(tableID, rows = 5) {
    let $table = $(tableID);
    return $table.DataTable({
        language: {
            lengthMenu: '_MENU_',
            search: '',
            searchPlaceholder: '{lang key='search' section='datatables'}',
            // ... weitere Übersetzungen
        },
        columns: [
            { data: 'sort' },      // Hidden: OrderNo für Filter
            { data: 'product' }    // Product-HTML
        ],
        lengthMenu: [[5, 10, 15, 30, 50], [5, 10, 15, 30, 50]],
        pageLength: 5,
        order: [0, 'desc'],      // Neueste Bestellungen zuerst
        drawCallback: () => {
            $table.find('thead').remove(); // Thead entfernen
        }
    });
}
```

#### Custom-Filter: Bestellnummer

```javascript
// Filter-Extension für DataTables
$.fn.dataTable.ext.search.push(function (settings, data) {
    let orderNo = customFilter.val(),
        orderNos = data[0] || '';  // Column 0 = orderNo
    
    return orderNo === orderNos || orderNo === '';
});

customFilter.on('change', function () {
    $table.draw();  // Tabelle neu filtern
});
```

#### Toggle-Logik: Checkboxen

```javascript
function setListenerForToggles() {
    $('.ra-switch').on('change', function () {
        if ($(this).prop('checked')) {
            // Zeige Mengen/Grund-Felder
            $(this).closest('tr').find('.rmaFormItems')
                .removeClass('d-none').addClass('d-flex');
        } else {
            // Verstecke Felder
            $(this).closest('tr').find('.rmaFormItems')
                .removeClass('d-flex').addClass('d-none');
        }
        $('#rma').submit();  // Ajax-Update
    });
}
```

#### Mengen-Stepper (Increment/Decrement)

```javascript
$('.qty-wrapper .btn-increment').on('click', function () {
    let input = $(this).closest('.qty-wrapper').find('input.quantity'),
        step = parseFloat(input.attr('step')),
        min = parseFloat(input.attr('min')),
        max = parseFloat(input.attr('data-max')),
        val = parseFloat(input.val());
    
    val += step;  // Erhöhen
    
    // Validierung: Max-Menge
    if (val > max) {
        let errorMessage = $('<div class="form-warning-msg"/>').html(
            '{lang key='maxAnzahlTitle' section='rma'}'.replace('%s', (val - max))
        );
        $(this).closest('.qty-wrapper').append(errorMessage);
    }
    
    input.val(val.toFixed(decimalPlaces));
    input.trigger('change');  // Ajax-Update
});
```

#### 2-Schritt-Navigation

```javascript
function step(goTo) {
    if (goTo === 1) {
        $('.rma-step-1').removeClass('d-none');
        $('.rma-step-2').addClass('d-none');
    } else if (goTo === 2) {
        $('.rma-step-2').removeClass('d-none');
        $('.rma-step-1').addClass('d-none');
        setListenerForBackButton();
    }
}
```

#### Ajax-Request: Formular-Submit

```javascript
$('#rma').on('submit', function (e) {
    e.preventDefault();
    setFormData($(this).serializeArray(), $table);
    
    // Validierung: Mindestens 1 Item
    if (formData.filter(e => e.name === 'quantity').length === 0) {
        showMinItemsAlert();
        return;
    }
    
    // Validierung: Retourengrund gesetzt
    let reasonNotSetInputs = formData.filter(
        input => input.name === 'reason' && input.value.value === '-1'
    );
    if (reasonNotSetInputs.length > 0) {
        showNoReasonAlert($table);
        return;
    }
    
    $('#rmaStickyItems').addClass('loadingAJAX');
    
    $.evo.io().request(
        {
            name: (goToStep === 2) ? 'rmaSummary' : 'rmaItems',
            params: [formData]
        },
        {},
        function (error, data) {
            $('#rmaStickyItems').removeClass('loadingAJAX');
            if (data['response']['result']) {
                if (goToStep === 1) {
                    // Update Sidebar
                    $('#rmaStickyItems .rmaItemContainer').html(data['response']['html']);
                } else {
                    // Zeige Summary
                    $('#rma-summary').html(data['response']['html']);
                    step(2);
                }
            }
        }
    );
});
```

#### FormData-Sammlung

```javascript
function setFormData(data, $table) {
    let inputs = [];
    
    $table.rows().every(function () {
        if ($(this.node()).find('input[name=returnItem]').prop('checked')) {
            $(this.node()).find('[data-snposid]').each(function () {
                inputs.push({
                    name: $(this).attr('name'),
                    value: {
                        posUniqueID: $(this).attr('data-snposid'),
                        value: $(this).val()
                    }
                });
            });
        }
    });
    
    formData = data.concat(inputs);
}
```

### Modal: Neue Rücksendeadresse

```smarty
{modal id="returnAddressModal" title={lang key='newReturnAddress'}}
    {include file='account/shipping_address_form.tpl' isModal=true}
{/modal}
```

**Trigger:**
```javascript
if ($('#returnAddress').val() === '-1') {
    $('#returnAddressModal').modal('show');
}
```

**Ajax-Submit:**
```javascript
$('#lieferadressen').on('submit', function (e) {
    e.preventDefault();
    $.evo.io().request(
        { name: 'createShippingAddress', params: [formData] },
        {},
        function (error, data) {
            if (data['response']['result']) {
                // Update Dropdown
                $('#returnAddress').html(data['response']['options']);
                $('#returnAddressModal').modal('hide');
                $('#rma button[type=submit]').trigger('click');
            }
        }
    );
});
```

---

## 2. rma_table.tpl (Produktauswahl-Tabelle)

**Zweck:** DataTable mit Checkboxen, Mengenfeldern, Grund-Dropdown

### HTML-Struktur

```smarty
<table id="returnable-items" class="table compact">
    <tbody>
    {foreach $returnableProducts as $product}
        {assign var=rmaItem value=$rmaService->getRMAItem($rma, $product->shippingNotePosID)}
        {assign var=itemUniqueID value="{$product->shippingNotePosID}_{$product->id}"}
        
        <tr>
            <td class="d-none">{$product->getOrderNo()}</td>
            <td class="product px-0">
                <div class="d-flex">
                    {* Produktbild *}
                    {image src=$product->getProduct()->Bilder[0]->cURLKlein}
                    
                    <div>
                        {* Produktname + Checkbox *}
                        <a href="{$product->getSeo()}">{$product->name}</a>
                        <input type='checkbox' 
                               class='ra-switch' 
                               id="switch-{$itemUniqueID}"
                               {if $rmaItem->id > 0}checked{/if}>
                        
                        {* Details *}
                        <small>
                            Bestellnummer: {$product->getOrderNo()}<br>
                            Produktnummer: {$product->getProductNo()}<br>
                            {if $product->variationName}
                                {$product->variationName}: {$product->variationValue}
                            {/if}
                        </small>
                        
                        {* Formular-Felder (nur wenn gechecked) *}
                        <div class="{if $rmaItem->id > 0}d-flex{else}d-none{/if} rmaFormItems">
                            {* Mengen-Stepper *}
                            {inputgroup class="form-counter"}
                                {button class="btn-decrement"}
                                    <span class="fas fa-minus"></span>
                                {/button}
                                {input type="number" 
                                       name="quantity"
                                       step="{if $product->getProduct()->cTeilbar === 'Y'}.01{else}1{/if}"
                                       min="1"
                                       value="{$rmaItem->quantity}"
                                       data-snposid="{$itemUniqueID}"
                                       data-max="{$product->quantity}"}
                                <div class="unit">{$product->unit}</div>
                                {button class="btn-increment"}
                                    <span class="fas fa-plus"></span>
                                {/button}
                            {/inputgroup}
                            
                            {* Retourengrund *}
                            {select name="reason" data-snposid="{$itemUniqueID}"}
                                <option value="-1">Bitte wählen</option>
                                {foreach $reasons as $reason}
                                    <option value="{$reason->reasonID}"
                                            {if $rmaItem->reasonID === $reason->reasonID}selected{/if}>
                                        {$reason->title}
                                    </option>
                                {/foreach}
                            {/select}
                            
                            {* Kommentar *}
                            {textarea name="comment" 
                                      data-snposid="{$itemUniqueID}"
                                      placeholder="Kommentar"}
                                {$rmaItem->comment}
                            {/textarea}
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    {/foreach}
    </tbody>
</table>
```

### Wichtige Patterns

#### `data-snposid` Attribut

**Zweck:** Eindeutige Identifikation der Lieferschein-Position

```smarty
{assign var=itemUniqueID value="{$product->shippingNotePosID}_{$product->id}"}

<input name="quantity" data-snposid="{$itemUniqueID}" />
<select name="reason" data-snposid="{$itemUniqueID}"></select>
<textarea name="comment" data-snposid="{$itemUniqueID}"></textarea>
```

**JavaScript-Nutzung:**
```javascript
$('[data-snposid]').each(function () {
    inputs.push({
        name: $(this).attr('name'),
        value: {
            posUniqueID: $(this).attr('data-snposid'),
            value: $(this).val()
        }
    });
});
```

#### Teilbare Artikel (Dezimalmengen)

```smarty
{input type="number"
       step="{if $product->getProduct()->cTeilbar === 'Y' && $product->getProduct()->fAbnahmeintervall == 0}.01{elseif $product->getProduct()->fAbnahmeintervall > 0}{$product->getProduct()->fAbnahmeintervall}{else}1{/if}"
       min="1"}
```

**Logik:**
- `cTeilbar === 'Y'` → Step 0.01 (Dezimalmengen)
- `fAbnahmeintervall > 0` → Step = Abnahmeintervall (z.B. 0.5)
- Sonst → Step 1 (Ganzzahlig)

#### Max-Mengen-Validierung

```smarty
{input data-max="{$product->quantity}"}
```

**JavaScript:**
```javascript
let max = parseFloat(input.attr('data-max')),
    val = parseFloat(input.val());

if (val > max) {
    let errorMessage = $('<div class="form-warning-msg"/>').html(
        'Sie haben %s mehr gewählt als verfügbar'.replace('%s', (val - max))
    );
    // Warnung anzeigen
}
```

---

## 3. rma_itemlist.tpl (Sticky-Sidebar: Gewählte Items)

**Zweck:** Live-Übersicht der gewählten Artikel (Ajax-Update)

### HTML-Struktur

```smarty
{if isset($rmaItems)}
    {cardheader}
        <span class="h3">{lang key='rma_products'}</span>
        <span class="badge badge-secondary">{count($rmaItems)}</span>
    {/cardheader}
    
    <ul class="list-group" id="rma-sticky-item-list">
        {foreach $rmaItems as $item}
            <li class="list-group-item">
                <h6 class="line-clamp line-clamp-2">{$item->name}</h6>
                <small>Menge: {$item->quantity}{$item->unit}</small>
            </li>
        {/foreach}
    </ul>
{/if}
```

### Ajax-Update

**Trigger:** Jede Checkbox-/Mengen-/Grund-Änderung

**Request:**
```javascript
$.evo.io().request(
    { name: 'rmaItems', params: [formData] },
    {},
    function (error, data) {
        $('#rmaStickyItems .rmaItemContainer').html(data['response']['html']);
    }
);
```

**Controller-Response:**
```php
return [
    'result' => true,
    'html' => $smarty->fetch('account/rma_itemlist.tpl', [
        'rmaItems' => $rma->getRMAItems()
    ])
];
```

---

## 4. rma_summary.tpl (Zusammenfassung vor Submit)

**Zweck:** Finale Übersicht + Bestätigung

### Smarty-Blöcke

| Block-Name | Zweck |
|------------|-------|
| `account-rma-summary` | Haupt-Container |
| `account-rma-summary-title` | Titel |
| `account-rma-summary-text` | Beschreibungstext |
| `account-rma-summary-address` | Rücksendeadresse |
| `account-rma-summary-history` | Changelog (bei bestehender RMA) |
| `account-rma-summary-items` | Item-Liste gruppiert |
| `account-rma-summary-buttons` | Buttons (Zurück, Absenden) |

### HTML-Struktur

```smarty
{row}
    {col}
        <h2>{lang key='rmaSummaryTitle'}</h2>
        <p>{lang key='rmaSummaryText'}</p>
        
        {* Rücksendeadresse *}
        {$returnAddress = $rmaReturnAddressService->getReturnAddress($rma)}
        {if $returnAddress->companyName}
            <h4>{$returnAddress->companyName}</h4>
        {/if}
        <p>
            {$returnAddress->firstName} {$returnAddress->lastName}<br>
            {$returnAddress->street} {$returnAddress->houseNumber}<br>
            {$returnAddress->postalCode} {$returnAddress->city}<br>
            {if $returnAddress->phone}Tel: {$returnAddress->phone}{/if}
        </p>
    {/col}
    
    {* Historie (falls vorhanden) *}
    {if isset($rmaHistory) && count($rmaHistory) > 0}
        {col}
            {card}
                <h5><i class="fas fa-clipboard-list"></i> Changelog</h5>
                {foreach $rmaHistory as $historyEvent}
                    {$eventData = $rmaHistoryService->getLocalizedEventDataAsObject($historyEvent)}
                    <div>
                        <h5>{$eventData->eventName}</h5>
                        <p>{$eventData->localizedText}</p>
                        <div class="badge badge-light">
                            {$eventData->dateObject->day}
                            {strtoupper($eventData->dateObject->month)}
                        </div>
                    </div>
                {/foreach}
            {/card}
        {/col}
    {/if}
{/row}

{* Items gruppiert nach Bestellung *}
<h3>{lang key='rmaSummaryItemTableTitle'}</h3>

{foreach $rmaService->groupRMAItems($rma->getRMAItems()) as $orderNo => $order}
    {card}
        <div class="card-header">
            <u>Bestellung {$orderNo}</u>
        </div>
        <div class="card-body">
            {* Header *}
            <div class="row font-weight-bold">
                <div class="col-auto">Bild</div>
                <div class="col">Name</div>
                <div class="col col-auto">Menge</div>
                <div class="col col-3">Grund</div>
            </div>
            
            {* Items *}
            {foreach $order as $pos}
                <div class="row">
                    <div class="col-auto">
                        {include file='snippets/image.tpl' item=$pos->getProduct()}
                    </div>
                    <div class="col">
                        <a href="{$pos->getProduct()->cSeo}">{$pos->name}</a>
                        <small>
                            {if $pos->variationName}
                                {$pos->variationName}: {$pos->variationValue}
                            {/if}
                            {if $pos->comment}
                                Kommentar: <i>{$pos->comment}</i>
                            {/if}
                        </small>
                    </div>
                    <div class="col col-auto">
                        {$pos->quantity}{$pos->unit}
                    </div>
                    <div class="col col-3">
                        {$pos->getReason()->title}
                    </div>
                </div>
            {/foreach}
        </div>
    {/card}
{/foreach}

{* Buttons *}
{if $showButtons|default:true}
    <div class="row mt-3">
        <div class="col col-md-6">
            <a href="#" id="goBackOneStep" class="btn btn-outline-primary">
                {lang key='edit'}
            </a>
        </div>
        <div class="col col-md-6">
            {form action="{get_static_route id='jtl.php'}"}
                <input type="hidden" name="rmaCreateDateHash" 
                       value="{$rmaService->hashCreateDate($rma)}">
                <button type="submit" class="btn btn-primary">
                    {lang key='createRetoure'}
                </button>
            {/form}
        </div>
    </div>
{/if}
```

### Wichtige Service-Calls

#### Gruppierung nach Bestellung

```smarty
{foreach $rmaService->groupRMAItems($rma->getRMAItems()) as $orderNo => $order}
```

**Service-Methode:**
```php
public function groupRMAItems(RMAItems $items): array {
    $grouped = [];
    foreach ($items as $item) {
        $orderNo = $item->getOrderNo();
        if (!isset($grouped[$orderNo])) {
            $grouped[$orderNo] = [];
        }
        $grouped[$orderNo][] = $item;
    }
    return $grouped;
}
```

#### Lokalisierte Event-Daten

```smarty
{$eventData = $rmaHistoryService->getLocalizedEventDataAsObject($historyEvent)}
```

**Service-Methode:**
```php
public function getLocalizedEventDataAsObject(RMAHistoryDomainObject $history): object {
    $eventData = json_decode($history->eventDataJson);
    return (object)[
        'eventName' => $this->getLocalizedEventName($history->eventName),
        'localizedText' => $this->getLocalizedText($eventData),
        'dateObject' => $this->parseDateObject($history->createDate)
    ];
}
```

#### CSRF-Protection: CreateDate-Hash

```smarty
<input type="hidden" name="rmaCreateDateHash" 
       value="{$rmaService->hashCreateDate($rma)}">
```

**Service-Methode:**
```php
public function hashCreateDate(RMADomainObject $rma): string {
    return hash('sha256', $rma->createDate . SECRET_KEY);
}
```

**Validierung:**
```php
public function validateHash(string $hash, string $createDate): bool {
    return hash_equals($hash, $this->hashCreateDate($createDate));
}
```

---

## 5. rma_list.tpl (Retouren-Übersicht)

**Zweck:** DataTable mit allen Retouren des Kunden

### HTML-Struktur

```smarty
{card}
    {cardheader}
        <h3>{lang key='myReturns'}</h3>
    {/cardheader}
    {cardbody}
        <table id="rmas-liste" class="table">
            <tbody>
            {foreach $rmaService->rmas as $rma}
                {$returnAddress = $rma->getReturnAddress()}
                <tr>
                    <td class="d-none">{$rma->createDate}</td>
                    <td>
                        {* Datum *}
                        <div><span class="far fa-calendar"></span>
                            {$rmaService::localizeDate($rma->createDate)}
                        </div>
                        
                        {* RMA-Nummer *}
                        <div>
                            RMA-ID: <span class="badge badge-primary">{$rma->rmaNr}</span>
                        </div>
                        
                        {* Status *}
                        {$status = $rmaService->getStatus($rma)}
                        <div>
                            Status: <span class="badge badge-{$status->class}">
                                {$status->text}
                            </span>
                        </div>
                        
                        {* Adresse *}
                        <div>
                            {if $returnAddress->companyName}
                                {$returnAddress->companyName}
                            {/if}
                            {$returnAddress->street} {$returnAddress->houseNumber}
                            {$returnAddress->postalCode} {$returnAddress->city}
                        </div>
                        
                        {* Modal: Item-Liste *}
                        {modal id="rmaAdditional{$rma->id}"}
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Artikel</th>
                                        <th>Menge</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {foreach $rma->getRMAItems() as $item}
                                    <tr>
                                        <td>
                                            {link href=$item->getSeo()}
                                                {include file='snippets/image.tpl' item=$item->getProduct()}
                                                {$item->name}
                                            {/link}
                                        </td>
                                        <td>{$item->quantity}{$item->unit}</td>
                                    </tr>
                                {/foreach}
                                </tbody>
                            </table>
                        {/modal}
                    </td>
                    <td class="text-right">
                        {* Buttons *}
                        <button data-toggle="modal" data-target="#rmaAdditional{$rma->id}">
                            <span class="fas fa-list-ol"></span>
                        </button>
                        <a href="{get_static_route id='jtl.php' params=['showRMA' => $rma->id]}">
                            <span class="far fa-eye"></span>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    {/cardbody}
{/card}
```

### DataTable-Konfiguration

```javascript
function initDataTable(table, rows = 5) {
    table.DataTable({
        language: {
            search: '',
            searchPlaceholder: '{lang key='search' section='datatables'}',
            // ...
        },
        columns: [
            { data: 'sort' },      // Hidden: createDate
            { data: 'address' },   // RMA-Details
            { data: 'buttons' }    // Action-Buttons
        ],
        pageLength: 5,
        order: [0, 'desc'],        // Neueste zuerst
        drawCallback: function() {
            table.find('thead').remove();
        }
    });
}

$(document).ready(function () {
    initDataTable($('#rmas-liste'));
});
```

### Status-Badge-Mapping

```smarty
{$status = $rmaService->getStatus($rma)}
<span class="badge badge-{$status->class}">{$status->text}</span>
```

**Service-Methode:**
```php
public function getStatus(RMADomainObject $rma): object {
    $statusMap = [
        1 => ['text' => 'Offen', 'class' => 'warning'],
        2 => ['text' => 'In Bearbeitung', 'class' => 'info'],
        3 => ['text' => 'Akzeptiert', 'class' => 'success'],
        4 => ['text' => 'Abgeschlossen', 'class' => 'secondary'],
        5 => ['text' => 'Abgelehnt', 'class' => 'danger']
    ];
    
    return (object)$statusMap[$rma->status];
}
```

---

## UI-Komponenten & Patterns

### Bootstrap 4 Komponenten

| Komponente | Verwendung | Template |
|------------|------------|----------|
| `{card}` | Container für Bereiche | Alle |
| `{row}` / `{col}` | Grid-Layout | Alle |
| `{modal}` | Popup (Adresse, Items) | rma.tpl, rma_list.tpl |
| `{form}` / `{formgroup}` | Formulare | rma.tpl, rma_summary.tpl |
| `{button}` / `{buttongroup}` | Aktionen | Alle |
| `{select}` / `{input}` | Form-Controls | rma_table.tpl |
| `{badge}` | Status, Counts | rma_list.tpl, rma_summary.tpl |
| `{inputgroup}` | Input + Buttons | rma_table.tpl (Stepper) |

### Custom Smarty-Funktionen

#### `{lang}`
```smarty
{lang key='addItems' section='rma'}
```
→ Übersetzung aus `lang/german/rma.php`

#### `{link}`
```smarty
{link href=$product->getSeo() title=$product->name}
    {$product->name}
{/link}
```
→ SEO-freundlicher Link mit Title

#### `{image}`
```smarty
{image lazy=true webp=true fluid=true
       src=$product->Bilder[0]->cURLKlein
       alt=$product->name}
```
→ Responsive Bild mit Lazy-Loading

#### `{include}`
```smarty
{include file='account/rma_table.tpl' returnableProducts=$returnableProducts}
```
→ Sub-Template mit Variablen

#### `{get_static_route}`
```smarty
{get_static_route id='jtl.php' params=['showRMA' => $rma->id]}
```
→ URL-Generierung mit Parametern

### CSS-Klassen

#### Utility-Klassen (JTL Custom)

```css
.text-muted-util       /* Grauer Text */
.text-right-util       /* Rechtsbündig */
.text-nowrap-util      /* Kein Umbruch */
.align-items-center-util  /* Vertikal zentriert */
```

#### Custom-Klassen

```css
.rma-step-1, .rma-step-2    /* Step-Wechsel */
.sticky-card                 /* Sticky-Sidebar */
.loadingAJAX                 /* Loading-Spinner */
.line-clamp, .line-clamp-2  /* Mehrzeiliger Ellipsis */
.form-counter               /* Mengen-Stepper */
.form-warning-msg           /* Fehlermeldung */
.limit-rows                 /* Expandable Cards */
```

---

## Ajax-Kommunikation

### JTL evo.io API

```javascript
$.evo.io().request(
    {
        name: 'methodName',     // PHP Controller-Methode
        params: [array]         // Parameter-Array
    },
    {},                         // Options (meist leer)
    function (error, data) {    // Callback
        if (error) {
            // Fehlerbehandlung
        }
        if (data['response']['result']) {
            // Erfolg
        }
    }
);
```

### Ajax-Endpunkte

| Name | Zweck | Request | Response |
|------|-------|---------|----------|
| `rmaItems` | Update Sidebar | `formData` | `{ result: true, html: '<ul>...</ul>' }` |
| `rmaSummary` | Load Summary | `formData` | `{ result: true, html: '<div>...</div>' }` |
| `createShippingAddress` | Neue Adresse | `formData` | `{ result: true, options: '<option>...</option>' }` |

### Request-Format: `formData`

```javascript
formData = [
    { name: 'returnAddress', value: '5' },
    { 
        name: 'quantity', 
        value: { 
            posUniqueID: '123_456', 
            value: '2' 
        } 
    },
    { 
        name: 'reason', 
        value: { 
            posUniqueID: '123_456', 
            value: '3' 
        } 
    },
    { 
        name: 'comment', 
        value: { 
            posUniqueID: '123_456', 
            value: 'Defekt' 
        } 
    }
]
```

---

## Validierungen

### Client-Side (JavaScript)

#### 1. Mindestens 1 Item

```javascript
if (formData.filter(e => e.name === 'quantity').length === 0) {
    eModal.alert({
        message: '{lang key='noItemsSelectedText' section='rma'}',
        title: '{lang key='noItemsSelectedTitle' section='rma'}'
    });
    return;
}
```

#### 2. Retourengrund gesetzt

```javascript
let reasonNotSetInputs = formData.filter(
    input => input.name === 'reason' && input.value.value === '-1'
);

if (reasonNotSetInputs.length > 0) {
    showNoReasonAlert($table);
    return;
}
```

**Modal-Lösung:**
```javascript
function showNoReasonAlert($table) {
    eModal.alert({
        message: '<p>Bitte wählen Sie einen Grund</p>' +
                 '<select id="reasonForAll">...</select>',
        buttons: [{
            text: 'Für alle übernehmen',
            click: function() {
                let newValue = $('#reasonForAll').val();
                $table.rows().every(function () {
                    let $select = $(this.node()).find('select[name=reason]');
                    if ($select.val() === '-1') {
                        $select.val(newValue);
                    }
                });
                $('#rma button[type=submit]').trigger('click');
            }
        }]
    });
}
```

#### 3. Max-Menge

```javascript
let max = parseFloat(input.attr('data-max')),
    val = parseFloat(input.val());

if (val > max) {
    // Auto-Korrektur
    input.val(max);
    
    // Warnung
    let warning = $('<div class="form-warning-msg"/>').html(
        'Sie haben ' + (val - max) + ' mehr gewählt als verfügbar'
    );
    $(this).closest('.qty-wrapper').append(warning);
}
```

#### 4. Rücksendeadresse

```javascript
if ($('#returnAddress').val() === '-1') {
    $('#returnAddressModal').modal('show');
    return;
}
```

---

## Responsive Design

### Breakpoints

```smarty
{col cols=12 md=7 lg=8}   {* Mobile: 100%, Tablet: 58%, Desktop: 66% *}
{col cols=12 md=5 lg=4}   {* Mobile: 100%, Tablet: 42%, Desktop: 33% *}
```

### Mobile-spezifische Anzeige

```smarty
<div class="col col-auto d-none d-md-block">  {* Ab Tablet sichtbar *}
    {$item->quantity}
</div>
```

### DataTable Responsive

```javascript
initComplete: function() {
    // Mobile: Filter full-width
    $tableWrapper.find('.dataTable-custom-filter')
        .removeClass('col-sm-12 col-md-6')
        .addClass('col-8 col-sm-8 col-md-8 col-lg-4');
}
```

---

## Wiederverwendbarkeit für Customer Returns Plugin

### ✅ Vollständig nutzbar

1. **rma.tpl** - Hauptstruktur adaptierbar
2. **rma_table.tpl** - DataTable-Pattern übernehmen
3. **rma_itemlist.tpl** - Sidebar-Update-Logik
4. **rma_summary.tpl** - Zusammenfassung-Layout
5. **rma_list.tpl** - Übersichts-DataTable

### 🔧 Anpassungen erforderlich

#### Controller-Methoden umbenennen

```javascript
// Aktuell:
$.evo.io().request({ name: 'rmaItems', ... })

// Plugin:
$.evo.io().request({ name: 'customerReturnItems', ... })
```

#### Namespace-Anpassung

```smarty
{* Aktuell *}
{include file='account/rma_table.tpl'}

{* Plugin *}
{include file='plugins/jtl_customer_returns/frontend/templates/return_table.tpl'}
```

#### Ajax-URL

```javascript
// Plugin-spezifische URL
let pluginUrl = '{get_static_route id='plugin.php' params=['plugin' => 'jtl_customer_returns']}';

$.ajax({
    url: pluginUrl + '/ajax/updateItems',
    // ...
});
```

### 🎨 Empfohlene Verbesserungen

#### 1. Ladeanimation

```smarty
<div id="rmaStickyItems" class="loadingAJAX">
    {* Spinner-HTML *}
</div>
```

```css
.loadingAJAX::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
```

#### 2. Optimistic UI

```javascript
// Sofort UI updaten (vor Ajax-Response)
$('.rmaItemContainer').html(generatePreviewHTML(formData));

// Bei Fehler: Rollback
$.evo.io().request(..., function(error, data) {
    if (error) {
        $('.rmaItemContainer').html(previousHTML);
    }
});
```

#### 3. Debounce für Mengen-Inputs

```javascript
let debounceTimer;
$('input.quantity').on('input', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        $('#rma').submit();
    }, 500);  // 500ms Verzögerung
});
```

---

## Nächste Schritte

1. ✅ **Templates analysiert**
2. ⬜ **JTL Demo-Plugin klonen** - Plugin-Struktur
3. ⬜ **Controller-Actions designen** - Backend-Logik
4. ⬜ **Helper-Klassen** - `RMAItems`, Events

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** Frontend-Templates vollständig dokumentiert. **2-Schritt-Prozess** verstanden. **DataTables + Ajax** analysiert. **Bootstrap 4 Patterns** extrahiert. Templates **wiederverwendbar** mit Anpassungen.
