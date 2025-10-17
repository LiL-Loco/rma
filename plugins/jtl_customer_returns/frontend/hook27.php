<?php
/**
 * Hook 27: HOOK_JTL_PAGE_MEINKKONTO
 * 
 * Wird auf der Kundenkonto-Hauptseite ausgeführt.
 * Fügt das Retouren-Widget in das Kundenkonto ein.
 * 
 * @param array $args_arr - Hook-Argumente
 */

declare(strict_types=1);

use JTL\Shop;
use Plugin\jtl_customer_returns\Repositories\RMARepository;

$customer = Shop::Container()->getCustomerService()->getLoggedInCustomer();

if (!$customer) {
    return;
}

try {
    $rmaRepo = new RMARepository();
    $rmas = $rmaRepo->getByCustomerID($customer->kKunde);
    
    // Nur die letzten 5 Retouren anzeigen
    $rmas = array_slice($rmas, 0, 5);
    
    // Offene Retouren zählen
    $openRMAsCount = count(array_filter($rmas, fn($rma) => $rma->getStatus() < 3));
    
    $smarty = Shop::Smarty();
    $smarty->assign('customerRMAs', $rmas);
    $smarty->assign('openRMAsCount', $openRMAsCount);
    
    $plugin = Shop::Container()->getPluginLoader()->getPluginById('jtl_customer_returns');
    $templatePath = $plugin->getPaths()->getFrontendPath() . 'template/my_returns_widget.tpl';
    
    $html = $smarty->fetch($templatePath);
    
    // Widget in Seite einfügen (über PHPQuery)
    if (function_exists('pq')) {
        pq('.account-orders-wrapper')->after($html);
    }
    
} catch (Exception $e) {
    Shop::Container()->getLogService()->error(
        "Hook 27 - Fehler beim Rendern des Retouren-Widgets: {$e->getMessage()}"
    );
}
