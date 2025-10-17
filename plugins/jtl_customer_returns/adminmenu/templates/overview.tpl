<div class="container-fluid">
    <h1><i class="fa fa-undo"></i> Retouren-Verwaltung</h1>
    
    <!-- Statistik-Karten -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Gesamt</h5>
                    <h2>{$stats.total}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Offen</h5>
                    <h2>{$stats.open}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>In Bearbeitung</h5>
                    <h2>{$stats.in_progress}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Akzeptiert</h5>
                    <h2>{$stats.accepted}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5>Abgeschlossen</h5>
                    <h2>{$stats.completed}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Abgelehnt</h5>
                    <h2>{$stats.rejected}</h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter & Suche -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <div class="form-group mr-3">
                    <label class="mr-2">Status-Filter:</label>
                    <select name="filter" class="form-control" onchange="this.form.submit()">
                        <option value="all" {if $filter === 'all'}selected{/if}>Alle</option>
                        <option value="0" {if $filter === '0'}selected{/if}>Offen</option>
                        <option value="1" {if $filter === '1'}selected{/if}>In Bearbeitung</option>
                        <option value="2" {if $filter === '2'}selected{/if}>Akzeptiert</option>
                        <option value="3" {if $filter === '3'}selected{/if}>Abgeschlossen</option>
                        <option value="4" {if $filter === '4'}selected{/if}>Abgelehnt</option>
                    </select>
                </div>
                
                <div class="form-group mr-3">
                    <label class="mr-2">Suche:</label>
                    <input type="text" name="search" class="form-control" placeholder="RMA-Nummer..." value="{$search|escape:'html'}">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Filtern
                </button>
                
                <a href="?filter=all" class="btn btn-secondary ml-2">
                    <i class="fa fa-times"></i> Zurücksetzen
                </a>
            </form>
        </div>
    </div>
    
    <!-- Retouren-Tabelle -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Retouren-Liste ({$rmas|count} Ergebnisse)</h5>
        </div>
        <div class="card-body">
            {if empty($rmas)}
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> Keine Retouren gefunden.
                </div>
            {else}
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="rma-table">
                        <thead>
                            <tr>
                                <th>RMA-Nr.</th>
                                <th>Erstellt</th>
                                <th>Kunde</th>
                                <th>Bestellung</th>
                                <th>Artikel</th>
                                <th>Status</th>
                                <th>Wert</th>
                                <th>Sync</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $rmas as $rmaData}
                            {assign var="rma" value=$rmaData.rma}
                            <tr>
                                <td>
                                    <strong>{$rma->getRmaNr()}</strong>
                                </td>
                                <td>
                                    {$rma->getCreateDate()|date_format:'%d.%m.%Y %H:%M'}
                                </td>
                                <td>
                                    Kunde #{$rma->getCustomerID()}
                                </td>
                                <td>
                                    Bestellung #{$rma->getOrderID()}
                                </td>
                                <td>
                                    {$rmaData.itemCount} Artikel ({$rmaData.totalQty} Stk.)
                                </td>
                                <td>
                                    <span class="badge badge-{$rma->getStatusColor()}">
                                        {$rma->getStatusText()}
                                    </span>
                                </td>
                                <td>
                                    {$rma->getTotalGross()|number_format:2:',':'.'} €
                                </td>
                                <td class="text-center">
                                    {if $rma->getSynced() === 1}
                                        <i class="fa fa-check text-success" title="Synchronisiert"></i>
                                    {else}
                                        <i class="fa fa-clock text-warning" title="Nicht synchronisiert"></i>
                                    {/if}
                                </td>
                                <td>
                                    <a href="?action=edit&rmaID={$rma->getId()}" 
                                       class="btn btn-sm btn-primary" 
                                       title="Bearbeiten">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    
                                    {if $rma->getLabelPath()}
                                    <a href="?action=download-label&rmaID={$rma->getId()}" 
                                       class="btn btn-sm btn-secondary" 
                                       title="Label herunterladen"
                                       target="_blank">
                                        <i class="fa fa-download"></i>
                                    </a>
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#rma-table').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/de-DE.json"
        },
        "order": [[1, "desc"]],
        "pageLength": 25
    });
});
</script>
