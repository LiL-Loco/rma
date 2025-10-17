{**
 * Template für Retoure-Button in Bestelldetails (Kundenkonto)
 * 
 * Verfügbare Variablen:
 * - $order: Bestellobjekt
 * - $isReturnable: Boolean, ob Bestellung retournierbar ist
 * - $returnPeriodDays: Retourenfrist in Tagen
 * - $existingRMA: Vorhandene RMA (falls bereits erstellt)
 *}

{if $isReturnable}
    <div class="return-button-wrapper mt-3">
        {if !$existingRMA}
            {* Neue Retoure anlegen *}
            <a href="/plugin/customer_returns/create?orderID={$order->kBestellung}" 
               class="btn btn-outline-warning btn-block">
                <i class="fa fa-undo me-2"></i> Retoure für diese Bestellung anlegen
            </a>
            
            <p class="text-muted small mt-2 mb-0">
                <i class="fa fa-info-circle"></i> 
                Sie können Artikel innerhalb von {$returnPeriodDays} Tagen nach Erhalt retournieren.
            </p>
        {else}
            {* Bestehende Retoure anzeigen *}
            <div class="alert alert-info">
                <h6 class="alert-heading mb-2">
                    <i class="fa fa-undo me-2"></i> Retoure vorhanden
                </h6>
                
                <p class="mb-2">
                    <strong>RMA-Nr.:</strong> {$existingRMA->getRmaNr()}<br>
                    <strong>Status:</strong> 
                    {if $existingRMA->getStatus() === 0}
                        <span class="badge bg-warning text-dark">Offen</span>
                    {elseif $existingRMA->getStatus() === 1}
                        <span class="badge bg-info">In Bearbeitung</span>
                    {elseif $existingRMA->getStatus() === 2}
                        <span class="badge bg-success">Akzeptiert</span>
                    {elseif $existingRMA->getStatus() === 3}
                        <span class="badge bg-success">Abgeschlossen</span>
                    {elseif $existingRMA->getStatus() === 4}
                        <span class="badge bg-danger">Abgelehnt</span>
                    {/if}
                </p>
                
                <a href="/plugin/customer_returns/detail/{$existingRMA->getId()}" 
                   class="btn btn-sm btn-outline-info">
                    Details anzeigen <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        {/if}
    </div>
{else}
    {* Nicht retournierbar *}
    <div class="alert alert-light border mt-3">
        <p class="mb-0 text-muted small">
            <i class="fa fa-clock-o me-1"></i> 
            Die Retourenfrist für diese Bestellung ist abgelaufen.
        </p>
    </div>
{/if}
