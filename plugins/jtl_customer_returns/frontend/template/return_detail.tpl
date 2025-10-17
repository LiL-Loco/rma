{**
 * Template für RMA-Detailansicht (Kundenkonto)
 * 
 * Verfügbare Variablen:
 * - $rma: RMA Domain Object
 * - $items: Array mit RMA-Items
 * - $order: Bestellung
 * - $hasLabel: Boolean, ob Retourenlabel vorhanden
 *}

{extends file="layout/index.tpl"}

{block name="content"}
<div class="container my-4">
    <div class="row">
        <div class="col-12">
            {* Breadcrumb *}
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/kundenkonto">Kundenkonto</a></li>
                    <li class="breadcrumb-item"><a href="/plugin/customer_returns/my-returns">Meine Retouren</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{$rma->getRmaNr()}</li>
                </ol>
            </nav>
            
            {* Header *}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fa fa-undo me-2"></i> Retoure {$rma->getRmaNr()}
                </h1>
                
                <div>
                    {if $rma->getStatus() === 0}
                        <span class="badge bg-warning text-dark fs-6">Offen</span>
                    {elseif $rma->getStatus() === 1}
                        <span class="badge bg-info text-white fs-6">In Bearbeitung</span>
                    {elseif $rma->getStatus() === 2}
                        <span class="badge bg-success fs-6">Akzeptiert</span>
                    {elseif $rma->getStatus() === 3}
                        <span class="badge bg-success fs-6">Abgeschlossen</span>
                    {elseif $rma->getStatus() === 4}
                        <span class="badge bg-danger fs-6">Abgelehnt</span>
                    {/if}
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        {* Linke Spalte: Retouren-Details *}
        <div class="col-md-8">
            {* Bestellinformationen *}
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Bestellinformationen</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <strong>Bestellnummer:</strong><br>
                            {$order->cBestellNr}
                        </div>
                        <div class="col-sm-6 mb-2">
                            <strong>Bestelldatum:</strong><br>
                            {$order->dErstellt|date_format:'%d.%m.%Y %H:%M'}
                        </div>
                    </div>
                </div>
            </div>
            
            {* Retournierte Artikel *}
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Retournierte Artikel</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Artikel</th>
                                    <th class="text-center">Menge</th>
                                    <th>Grund</th>
                                    <th class="text-end">Betrag</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $items as $item}
                                    <tr>
                                        <td>
                                            {* TODO: Produktname aus tartikel laden *}
                                            Artikel #{$item->getProductID()}
                                            {if $item->getComment()}
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fa fa-comment me-1"></i>
                                                    {$item->getComment()}
                                                </small>
                                            {/if}
                                        </td>
                                        <td class="text-center">
                                            {$item->getQuantity()}x
                                        </td>
                                        <td>
                                            {* TODO: Grund aus rma_reasons laden *}
                                            Grund #{$item->getReasonID()}
                                        </td>
                                        <td class="text-end">
                                            {if $item->getRefundAmount() > 0}
                                                {$item->getRefundAmount()|number_format:2:',':'.'} €
                                            {else}
                                                <span class="text-muted">Ausstehend</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="3" class="text-end">Gesamt:</th>
                                    <th class="text-end">
                                        {$rma->getTotalGross()|number_format:2:',':'.'} €
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            {* Status-Historie *}
            {if $rma->getStatus() > 0}
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Status-Verlauf</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <strong>Retoure angelegt</strong><br>
                                    <small class="text-muted">{$rma->getCreateDate()|date_format:'%d.%m.%Y %H:%M'}</small>
                                </div>
                            </div>
                            
                            {if $rma->getStatus() >= 1}
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <strong>In Bearbeitung</strong><br>
                                        <small class="text-muted">{$rma->getUpdateDate()|date_format:'%d.%m.%Y %H:%M'}</small>
                                    </div>
                                </div>
                            {/if}
                            
                            {if $rma->getStatus() >= 2}
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <strong>Akzeptiert</strong><br>
                                        <small class="text-muted">{$rma->getUpdateDate()|date_format:'%d.%m.%Y %H:%M'}</small>
                                    </div>
                                </div>
                            {/if}
                            
                            {if $rma->getStatus() === 4}
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-danger"></div>
                                    <div class="timeline-content">
                                        <strong>Abgelehnt</strong><br>
                                        <small class="text-muted">{$rma->getUpdateDate()|date_format:'%d.%m.%Y %H:%M'}</small>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            {/if}
        </div>
        
        {* Rechte Spalte: Aktionen & Infos *}
        <div class="col-md-4">
            {* Retourenlabel *}
            {if $hasLabel}
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="fa fa-file-pdf-o me-2"></i> Retourenlabel
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">
                            Ihr Retourenlabel steht zum Download bereit.
                        </p>
                        
                        <a href="/plugin/customer_returns/download-label/{$rma->getId()}" 
                           class="btn btn-primary btn-block"
                           target="_blank">
                            <i class="fa fa-download me-2"></i> Label herunterladen
                        </a>
                        
                        <hr>
                        
                        <small class="text-muted">
                            <i class="fa fa-info-circle me-1"></i>
                            Bitte drucken Sie das Label aus und kleben Sie es auf das Paket.
                        </small>
                    </div>
                </div>
            {/if}
            
            {* Informationen *}
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Informationen</h6>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>RMA-Nummer</dt>
                        <dd class="text-muted">{$rma->getRmaNr()}</dd>
                        
                        <dt>Erstellt am</dt>
                        <dd class="text-muted">{$rma->getCreateDate()|date_format:'%d.%m.%Y %H:%M'}</dd>
                        
                        {if $rma->getSynced()}
                            <dt>Synchronisiert</dt>
                            <dd class="text-success">
                                <i class="fa fa-check-circle me-1"></i> Ja
                            </dd>
                            
                            {if $rma->getLastSyncDate()}
                                <dt>Letzter Sync</dt>
                                <dd class="text-muted">{$rma->getLastSyncDate()|date_format:'%d.%m.%Y %H:%M'}</dd>
                            {/if}
                        {else}
                            <dt>Synchronisiert</dt>
                            <dd class="text-warning">
                                <i class="fa fa-clock-o me-1"></i> Ausstehend
                            </dd>
                        {/if}
                    </dl>
                </div>
            </div>
            
            {* Hilfe *}
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fa fa-question-circle me-2"></i> Hilfe
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Fragen zur Retoure?</strong>
                    </p>
                    <p class="small mb-0">
                        Kontaktieren Sie unseren Kundenservice:<br>
                        <a href="mailto:service@shop.de">service@shop.de</a><br>
                        Tel: 0800 123 456
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    {* Zurück-Button *}
    <div class="row mt-4">
        <div class="col-12">
            <a href="/plugin/customer_returns/my-returns" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i> Zurück zur Übersicht
            </a>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -22px;
    top: 10px;
    bottom: -10px;
    width: 2px;
    background: #dee2e6;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-marker {
    position: absolute;
    left: -28px;
    top: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.timeline-content {
    padding-left: 0;
}
</style>
{/block}
