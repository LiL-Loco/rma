{extends file='layout/index.tpl'}

{block name='content'}
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-arrow-return-left"></i> Retoure anmelden</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Geben Sie Ihre Bestellnummer und E-Mail-Adresse ein, um eine Retoure anzumelden.
                    </p>
                    
                    <form id="return-form" method="POST">
                        <div class="mb-3">
                            <label for="order_number" class="form-label">Bestellnummer <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="order_number" 
                                   name="orderNo" 
                                   placeholder="z.B. 20240001"
                                   required>
                            <small class="form-text text-muted">Sie finden die Bestellnummer in Ihrer Bestell-Bestätigung.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail-Adresse <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="ihre@email.de"
                                   required>
                        </div>
                        
                        <div id="error-message" class="alert alert-danger d-none" role="alert"></div>
                        
                        <button type="submit" class="btn btn-primary btn-block" id="submit-btn">
                            <i class="bi bi-search"></i> Bestellung prüfen
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <p class="text-muted small">
                    <i class="bi bi-info-circle"></i> Sie haben <strong>14 Tage</strong> Zeit, eine Retoure anzumelden.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#return-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $('#submit-btn');
        var $errorDiv = $('#error-message');
        
        // Button deaktivieren
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Prüfe...');
        $errorDiv.addClass('d-none');
        
        $.ajax({
            url: '/plugin/customer_returns/validate-order',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect;
                } else {
                    $errorDiv.removeClass('d-none').text(response.error);
                    $submitBtn.prop('disabled', false).html('<i class="bi bi-search"></i> Bestellung prüfen');
                }
            },
            error: function() {
                $errorDiv.removeClass('d-none').text('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
                $submitBtn.prop('disabled', false).html('<i class="bi bi-search"></i> Bestellung prüfen');
            }
        });
    });
});
</script>
{/block}
