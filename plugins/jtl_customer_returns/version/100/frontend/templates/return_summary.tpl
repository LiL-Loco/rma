{extends file='layout/index.tpl'}

{block name='content'}
<div class="container my-5">
    <h2 class="mb-4"><i class="bi bi-clipboard-check"></i> Zusammenfassung Ihrer Retoure</h2>
    
    <div class="alert alert-info">
        <strong>Bestellung:</strong> {$orderNo}
    </div>
    
    {if isset($error)}
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> {$error}
    </div>
    {/if}
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Ausgewählte Artikel</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Artikel</th>
                        <th width="100">Menge</th>
                        <th width="200">Grund</th>
                        <th width="150">Kommentar</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $summaryItems as $item}
                    <tr>
                        <td>
                            <strong>{$item.product.name|escape:'html'}</strong><br>
                            <small class="text-muted">Art.-Nr.: {$item.product.articleNo}</small>
                        </td>
                        <td class="text-center">{$item.quantity}</td>
                        <td>{$item.reason|escape:'html'}</td>
                        <td>{$item.comment|escape:'html'|default:'-'}</td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Hinweise</h5>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Nach Absenden erhalten Sie eine Bestätigungs-E-Mail mit der Retourennummer.</li>
                <li>Bitte senden Sie die Artikel innerhalb von 7 Tagen zurück.</li>
                <li>Die Ware sollte unbenutzt und in Originalverpackung sein.</li>
                <li>Die Rückerstattung erfolgt nach Wareneingang (ca. 5-7 Werktage).</li>
            </ul>
        </div>
    </div>
    
    <form id="submit-return-form" method="POST" action="/plugin/customer_returns/submit">
        <div class="form-check mb-4">
            <input type="checkbox" class="form-check-input" id="accept-terms" required>
            <label class="form-check-label" for="accept-terms">
                Ich habe die <a href="#" target="_blank">Widerrufsbelehrung</a> gelesen und akzeptiere diese.
            </label>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <a href="/plugin/customer_returns/select-products" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Zurück zur Artikelauswahl
                </a>
            </div>
            <div class="col-md-6 text-right">
                <button type="submit" class="btn btn-success" id="submit-btn">
                    <i class="bi bi-check-circle"></i> Retoure verbindlich anmelden
                </button>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#submit-return-form').on('submit', function() {
        var $btn = $('#submit-btn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Wird gesendet...');
    });
});
</script>
{/block}
