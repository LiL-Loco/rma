{extends file='layout/index.tpl'}

{block name='content'}
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h1 class="mb-4"><i class="fa fa-undo"></i> Retoure anlegen</h1>
            
            <div class="card">
                <div class="card-body">
                    <p class="lead">Bitte geben Sie Ihre Bestellnummer und E-Mail-Adresse ein, um eine Retoure anzulegen.</p>
                    
                    <form id="return-form" method="POST" action="/plugin/customer_returns/validate">
                        {if isset($error)}
                        <div class="alert alert-danger">
                            {$error}
                        </div>
                        {/if}
                        
                        <div class="form-group mb-3">
                            <label for="order_number">Bestellnummer *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="order_number" 
                                   name="orderNo" 
                                   required 
                                   placeholder="z.B. 20250001">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="email">E-Mail-Adresse *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   placeholder="ihre@email.de">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa fa-search"></i> Bestellung suchen
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info mt-4">
                <strong>Hinweis:</strong> Die Bestellnummer finden Sie in Ihrer Bestellbestätigung.
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#return-form').on('submit', function(e) {
        e.preventDefault();
        
        var orderNo = $('#order_number').val();
        var email = $('#email').val();
        
        if (!orderNo || !email) {
            alert('Bitte füllen Sie alle Felder aus.');
            return;
        }
        
        $.ajax({
            url: '/plugin/customer_returns/validate',
            method: 'POST',
            data: {
                orderNo: orderNo,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect;
                } else {
                    alert(response.error || 'Bestellung konnte nicht gefunden werden.');
                }
            },
            error: function() {
                alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            }
        });
    });
});
</script>
{/block}
