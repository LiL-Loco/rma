<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns;

use JTL\Shop;

// Page-Type für Plugin setzen
Shop::setPageType(PAGE_PLUGIN);

// Controller instanziieren und Index-Action aufrufen
require_once __DIR__ . '/ReturnController.php';

$controller = new \Plugin\jtl_customer_returns\Controllers\ReturnController();
$response = $controller->actionIndex(
    \Laminas\Diactoros\ServerRequestFactory::fromGlobals(),
    new \Laminas\Diactoros\Response()
);

// Response ausgeben
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}
echo $response->getBody();
