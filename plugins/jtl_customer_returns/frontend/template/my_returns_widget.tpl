{**
 * Widget: Meine Retouren (Kundenkonto-Übersicht)
 * 
 * Verfügbare Variablen:
 * - $customerRMAs: Array mit RMA-Objekten des Kunden
 * - $openRMAsCount: Anzahl offener Retouren
 *}

{if $customerRMAs|@count > 0}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fa fa-undo me-2"></i> Meine Retouren
                {if $openRMAsCount > 0}
                    <span class="badge bg-warning text-dark ms-2">{$openRMAsCount}</span>
                {/if}
            </h5>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>RMA-Nr.</th>
                            <th>Bestellung</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $customerRMAs as $rma}
                            <tr>
                                <td>
                                    <strong>{$rma->getRmaNr()}</strong>
                                </td>
                                <td>
                                    {* Hier sollte die Bestellnummer geholt werden *}
                                    #{$rma->getOrderID()}
                                </td>
                                <td>
                                    {$rma->getCreateDate()|date_format:'%d.%m.%Y'}
                                </td>
                                <td>
                                    {if $rma->getStatus() === 0}
                                        <span class="badge bg-warning text-dark">Offen</span>
                                    {elseif $rma->getStatus() === 1}
                                        <span class="badge bg-info text-white">In Bearbeitung</span>
                                    {elseif $rma->getStatus() === 2}
                                        <span class="badge bg-success">Akzeptiert</span>
                                    {elseif $rma->getStatus() === 3}
                                        <span class="badge bg-success">Abgeschlossen</span>
                                    {elseif $rma->getStatus() === 4}
                                        <span class="badge bg-danger">Abgelehnt</span>
                                    {/if}
                                </td>
                                <td class="text-end">
                                    <a href="/plugin/customer_returns/detail/{$rma->getId()}" 
                                       class="btn btn-sm btn-outline-primary"
                                       title="Details anzeigen">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3 text-end">
                <a href="/plugin/customer_returns/my-returns" class="btn btn-sm btn-outline-secondary">
                    Alle Retouren anzeigen <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
{/if}
