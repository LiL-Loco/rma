{extends file='layout/index.tpl'}

{block name='content'}
<div class="container my-5">
    <h2 class="mb-4"><i class="bi bi-list-ul"></i> Meine Retouren</h2>
    
    {if empty($rmas)}
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Sie haben noch keine Retouren angelegt.
    </div>
    <div class="text-center">
        <a href="/plugin/customer_returns" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Neue Retoure anmelden
        </a>
    </div>
    {else}
    
    <div class="mb-3">
        <a href="/plugin/customer_returns" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Neue Retoure anmelden
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="thead-light">
                <tr>
                    <th>RMA-Nr.</th>
                    <th>Erstellt am</th>
                    <th>Artikel</th>
                    <th>Status</th>
                    <th>Gesamtwert</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                {foreach $rmas as $rmaData}
                {assign var="rma" value=$rmaData.rma}
                <tr>
                    <td><strong>{$rma->getRmaNr()}</strong></td>
                    <td>{$rma->getCreateDate()|date_format:'%d.%m.%Y'}</td>
                    <td>{$rmaData.itemCount} Artikel</td>
                    <td>
                        <span class="badge badge-{$rma->getStatusColor()}">
                            {$rma->getStatusText()}
                        </span>
                    </td>
                    <td>{$rma->getTotalGross()|number_format:2:',':'.'} €</td>
                    <td>
                        <a href="/plugin/customer_returns/detail/{$rma->getId()}" 
                           class="btn btn-sm btn-outline-primary" 
                           title="Details anzeigen">
                            <i class="bi bi-eye"></i>
                        </a>
                        {if $rma->getLabelPath() !== null}
                        <a href="/plugin/customer_returns/label/{$rma->getId()}" 
                           class="btn btn-sm btn-outline-secondary" 
                           title="Versandlabel herunterladen"
                           target="_blank">
                            <i class="bi bi-printer"></i>
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
{/block}
