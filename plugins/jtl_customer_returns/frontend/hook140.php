<?php
/**
 * Hook 140: HOOK_SMARTY_OUTPUTFILTER
 * 
 * Wird nach dem Rendern von Smarty-Templates ausgeführt.
 * Fügt den Retoure-Button in Bestelldetails ein.
 * 
 * @param array $args_arr - Hook-Argumente
 *   - 'smarty' => JTL\Smarty\JTLSmarty
 *   - 'document' => phpQueryObject (DOM)
 */

declare(strict_types=1);

use JTL\Shop;
use Plugin\jtl_customer_returns\Controllers\ReturnController;

// Nur auf Bestelldetail-Seite aktiv
if (Shop::getPageType() !== PAGE_BESTELLDETAILS) {
    return;
}

$customer = Shop::Container()->getCustomerService()->getLoggedInCustomer();

if (!$customer) {
    return;
}

try {
    $smarty = $args_arr['smarty'] ?? null;
    
    if (!$smarty) {
        return;
    }
    
    // Bestellung aus Smarty-Variable holen
    $order = $smarty->getTemplateVars('Bestellung');
    
    if (!$order || !isset($order->kBestellung)) {
        return;
    }
    
    // Controller instanziieren und Button-Daten holen
    $controller = new ReturnController();
    $buttonData = $controller->getReturnButtonData((int)$order->kBestellung);
    
    if (empty($buttonData)) {
        return;
    }
    
    // Template rendern
    $smarty->assign('isReturnable', $buttonData['isReturnable']);
    $smarty->assign('returnPeriodDays', $buttonData['returnPeriodDays']);
    $smarty->assign('existingRMA', $buttonData['existingRMA'] ?? null);
    $smarty->assign('order', $buttonData['order']);
    
    $plugin = Shop::Container()->getPluginLoader()->getPluginById('jtl_customer_returns');
    $templatePath = $plugin->getPaths()->getFrontendPath() . 'template/order_detail_return_button.tpl';
    
    $html = $smarty->fetch($templatePath);
    
    // Button nach Bestellstatus einfügen (über PHPQuery)
    if (function_exists('pq')) {
        // Versuche verschiedene Selektoren (abhängig vom Template)
        if (pq('.order-details .order-status')->length > 0) {
            pq('.order-details .order-status')->after($html);
        } elseif (pq('.order-status')->length > 0) {
            pq('.order-status')->after($html);
        } elseif (pq('.order-details')->length > 0) {
            pq('.order-details')->append($html);
        } else {
            // Fallback: Vor Footer einfügen
            pq('.content-wrapper')->append($html);
        }
    }
    
} catch (Exception $e) {
    Shop::Container()->getLogService()->error(
        "Hook 140 - Fehler beim Einfügen des Retoure-Buttons: {$e->getMessage()}"
    );
}
