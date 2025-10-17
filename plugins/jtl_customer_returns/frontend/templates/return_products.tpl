{extends file='layout/index.tpl'}

{block name='content'}
<div class="container my-5">
    <h2 class="mb-4"><i class="bi bi-box-seam"></i> Artikel für Retoure auswählen</h2>
    
    <div class="alert alert-info">
        <strong>Bestellung:</strong> {$orderNo} | 
        <strong>Gesamtwert retournierbar:</strong> {$totalValue|number_format:2:',':'.'} €
    </div>
    
    <form id="return-products-form" method="POST" action="/plugin/customer_returns/summary">
        <div class="table-responsive">
            <table class="table table-bordered" id="products-table">
                <thead class="thead-light">
                    <tr>
                        <th width="50"><input type="checkbox" id="select-all"></th>
                        <th>Artikel</th>
                        <th width="120">Verfügbar</th>
                        <th width="120">Menge</th>
                        <th width="200">Grund</th>
                        <th width="150">Kommentar</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $products as $product}
                    <tr data-product-id="{$product.productID}">
                        <td class="text-center">
                            <input type="checkbox" 
                                   class="product-checkbox" 
                                   name="selected[]" 
                                   value="{$product.productID}">
                        </td>
                        <td>
                            <strong>{$product.name|escape:'html'}</strong><br>
                            <small class="text-muted">Art.-Nr.: {$product.articleNo}</small>
                        </td>
                        <td class="text-center">{$product.availableQty}</td>
                        <td>
                            <input type="number" 
                                   class="form-control form-control-sm quantity-input" 
                                   name="items[{$product.productID}][quantity]" 
                                   min="1" 
                                   max="{$product.availableQty}" 
                                   value="1" 
                                   disabled>
                            <input type="hidden" name="items[{$product.productID}][productID]" value="{$product.productID}">
                            <input type="hidden" name="items[{$product.productID}][variationID]" value="{$product.variationID}">
                        </td>
                        <td>
                            <select class="form-control form-control-sm reason-select" 
                                    name="items[{$product.productID}][reasonID]" 
                                    disabled 
                                    required>
                                <option value="">Bitte wählen</option>
                                {foreach $returnReasons as $reason}
                                <option value="{$reason.id}">{$reason.reason|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </td>
                        <td>
                            <input type="text" 
                                   class="form-control form-control-sm" 
                                   name="items[{$product.productID}][comment]" 
                                   placeholder="Optional" 
                                   disabled>
                        </td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        
        <div id="validation-error" class="alert alert-warning d-none" role="alert">
            <i class="bi bi-exclamation-triangle"></i> Bitte wählen Sie mindestens einen Artikel aus.
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <a href="/plugin/customer_returns" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Zurück
                </a>
            </div>
            <div class="col-md-6 text-right">
                <button type="submit" class="btn btn-primary">
                    Weiter zur Zusammenfassung <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Select All Checkbox
    $('#select-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('.product-checkbox').prop('checked', checked).trigger('change');
    });
    
    // Product Checkbox Change
    $('.product-checkbox').on('change', function() {
        var $row = $(this).closest('tr');
        var checked = $(this).is(':checked');
        
        $row.find('.quantity-input, .reason-select, input[type="text"]').prop('disabled', !checked);
        
        if (!checked) {
            $row.find('.quantity-input').val(1);
            $row.find('.reason-select').val('');
        }
    });
    
    // Form Submit Validation
    $('#return-products-form').on('submit', function(e) {
        var selectedCount = $('.product-checkbox:checked').length;
        
        if (selectedCount === 0) {
            e.preventDefault();
            $('#validation-error').removeClass('d-none');
            $('html, body').animate({ scrollTop: 0 }, 300);
            return false;
        }
        
        // Validate Reason Selection
        var valid = true;
        $('.product-checkbox:checked').each(function() {
            var $row = $(this).closest('tr');
            var reason = $row.find('.reason-select').val();
            
            if (!reason) {
                valid = false;
                $row.find('.reason-select').addClass('is-invalid');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Bitte wählen Sie für alle Artikel einen Retourengrund aus.');
            return false;
        }
        
        return true;
    });
});
</script>
{/block}
