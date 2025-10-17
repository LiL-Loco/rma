<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-8">
            <h1><i class="fa fa-edit"></i> Retoure bearbeiten</h1>
        </div>
        <div class="col-md-4 text-right">
            <a href="?action=overview" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Zurück zur Übersicht
            </a>
        </div>
    </div>
    
    {if isset($message)}
        {$message}
    {/if}
    
    <!-- RMA-Informationen -->
    <div class="row">
        <div class="col-md-8">
            <!-- Haupt-Info Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">RMA-Informationen</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>RMA-Nummer:</strong> {$rma->getRmaNr()}</p>
                            <p><strong>Erstellt:</strong> {$rma->getCreateDate()|date_format:'%d.%m.%Y %H:%M'}</p>
                            <p><strong>Aktualisiert:</strong> {$rma->getUpdateDate()|date_format:'%d.%m.%Y %H:%M'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Bestellung:</strong> {$order.orderNo}</p>
                            <p><strong>Kunde:</strong> {$customer.fullName}</p>
                            <p><strong>E-Mail:</strong> {$customer.email}</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Status:</strong> 
                                <span class="badge badge-{$rma->getStatusColor()}">
                                    {$rma->getStatusText()}
                                </span>
                            </p>
                            <p><strong>Gesamtwert:</strong> {$rma->getTotalGross()|number_format:2:',':'.'} €</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Wawi-Sync:</strong> 
                                {if $rma->getSynced() === 1}
                                    <span class="text-success"><i class="fa fa-check"></i> Synchronisiert</span>
                                {else}
                                    <span class="text-warning"><i class="fa fa-clock"></i> Ausstehend</span>
                                {/if}
                            </p>
                            {if $rma->getWawiID()}
                            <p><strong>Wawi-ID:</strong> {$rma->getWawiID()}</p>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Artikel-Liste -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Retournierte Artikel</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Artikel-ID</th>
                                <th>Menge</th>
                                <th>Grund</th>
                                <th>Status</th>
                                <th>Rückerstattung</th>
                                <th>Kommentar</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $items as $item}
                            <tr>
                                <td>#{$item->getProductID()}</td>
                                <td>{$item->getQuantity()}</td>
                                <td>Grund #{$item->getReasonID()}</td>
                                <td>
                                    {assign var="itemStatus" value=$item->getItemStatus()}
                                    {if $itemStatus === 0}
                                        <span class="badge badge-warning">Ausstehend</span>
                                    {elseif $itemStatus === 1}
                                        <span class="badge badge-success">Akzeptiert</span>
                                    {elseif $itemStatus === 2}
                                        <span class="badge badge-danger">Abgelehnt</span>
                                    {elseif $itemStatus === 3}
                                        <span class="badge badge-info">Erstattet</span>
                                    {/if}
                                </td>
                                <td>{$item->getRefundAmount()|number_format:2:',':'.'} €</td>
                                <td>{$item->getComment()|escape:'html'|default:'-'}</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Historie -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Historie / Ereignisse</h5>
                </div>
                <div class="card-body">
                    {if empty($history)}
                        <p class="text-muted">Keine Ereignisse vorhanden.</p>
                    {else}
                        <div class="timeline">
                            {foreach $history as $event}
                            <div class="timeline-item mb-3">
                                <div class="row">
                                    <div class="col-md-2 text-muted small">
                                        {$event->getCreatedAt()|date_format:'%d.%m.%Y %H:%M'}
                                    </div>
                                    <div class="col-md-10">
                                        <strong>{$event->getEvent()}</strong>
                                        {if $event->getEventData()}
                                            <pre class="bg-light p-2 mt-2">{$event->getEventData()|@json_encode:128}</pre>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                            {/foreach}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
        
        <!-- Aktionen Sidebar -->
        <div class="col-md-4">
            <!-- Status ändern -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Status ändern</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="form-group">
                            <label>Neuer Status:</label>
                            <select name="status" class="form-control" required>
                                {foreach $statusOptions as $option}
                                <option value="{$option.value}" {if $rma->getStatus() == $option.value}selected{/if}>
                                    {$option.label}
                                </option>
                                {/foreach}
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Kommentar (optional):</label>
                            <textarea name="comment" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="send_email" name="send_email" value="1" checked>
                            <label class="form-check-label" for="send_email">
                                E-Mail an Kunden senden
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-save"></i> Status aktualisieren
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Versandlabel -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Versandlabel</h5>
                </div>
                <div class="card-body">
                    {if $rma->getLabelPath()}
                        <div class="alert alert-success">
                            <i class="fa fa-check"></i> Label vorhanden
                        </div>
                        <a href="?action=download-label&rmaID={$rma->getId()}" 
                           class="btn btn-secondary btn-block" 
                           target="_blank">
                            <i class="fa fa-download"></i> Label herunterladen
                        </a>
                    {else}
                        <form method="POST">
                            <input type="hidden" name="action" value="create_label">
                            <button type="submit" class="btn btn-warning btn-block">
                                <i class="fa fa-plus"></i> Label erstellen
                            </button>
                        </form>
                    {/if}
                </div>
            </div>
            
            <!-- E-Mail senden -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">E-Mail senden</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="send_email">
                        <input type="hidden" name="email_type" value="status_update">
                        
                        <button type="submit" class="btn btn-info btn-block">
                            <i class="fa fa-envelope"></i> Status-Update senden
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
