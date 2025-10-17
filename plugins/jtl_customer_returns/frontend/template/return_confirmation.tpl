{extends file='layout/index.tpl'}

{block name='content'}
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-success text-white text-center">
                    <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                    <h3 class="mt-2">Retoure erfolgreich angelegt!</h3>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h4>Ihre Retourennummer:</h4>
                        <div class="alert alert-light border-success">
                            <h2 class="mb-0 text-success">{$rmaNr}</h2>
                        </div>
                        <p class="text-muted">Bitte notieren Sie sich diese Nummer für Ihre Unterlagen.</p>
                    </div>
                    
                    <hr>
                    
                    <h5><i class="bi bi-info-circle"></i> Wie geht es weiter?</h5>
                    <ol class="mb-4">
                        <li class="mb-2">
                            <strong>Bestätigungs-E-Mail:</strong> 
                            Sie erhalten in Kürze eine E-Mail mit allen Details zur Retoure.
                        </li>
                        <li class="mb-2">
                            {if $hasLabel}
                            <strong>Versandlabel:</strong> 
                            Ein Retourenlabel liegt dieser E-Mail bei. Bitte ausdrucken und auf das Paket kleben.
                            {else}
                            <strong>Rücksendung:</strong> 
                            Senden Sie die Artikel an die in der E-Mail angegebene Adresse zurück.
                            {/if}
                        </li>
                        <li class="mb-2">
                            <strong>Bearbeitung:</strong> 
                            Nach Wareneingang wird Ihre Retoure geprüft (ca. 3-5 Werktage).
                        </li>
                        <li class="mb-2">
                            <strong>Rückerstattung:</strong> 
                            Die Erstattung erfolgt auf Ihr ursprüngliches Zahlungsmittel.
                        </li>
                    </ol>
                    
                    {if $hasLabel}
                    <div class="alert alert-warning">
                        <i class="bi bi-printer"></i> 
                        <strong>Tipp:</strong> Sie können das Versandlabel auch in Ihrem Kundenaccount unter 
                        <a href="/plugin/customer_returns/my-returns">Meine Retouren</a> herunterladen.
                    </div>
                    {/if}
                    
                    <div class="text-center mt-4">
                        <a href="/plugin/customer_returns/my-returns" class="btn btn-primary">
                            <i class="bi bi-list-ul"></i> Zu meinen Retouren
                        </a>
                        <a href="/" class="btn btn-secondary ml-2">
                            <i class="bi bi-house"></i> Zur Startseite
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <p class="text-muted small">
                    Bei Fragen zu Ihrer Retoure kontaktieren Sie uns bitte unter: 
                    <a href="mailto:support@shop.de">support@shop.de</a>
                </p>
            </div>
        </div>
    </div>
</div>
{/block}
