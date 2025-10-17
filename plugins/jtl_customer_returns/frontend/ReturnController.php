<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Controllers;

use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Plugin\jtl_customer_returns\Services\ReturnRequestService;
use Plugin\jtl_customer_returns\Services\NotificationService;
use Plugin\jtl_customer_returns\Services\ShippingLabelService;
use Plugin\jtl_customer_returns\Repositories\RMARepository;
use Plugin\jtl_customer_returns\Repositories\RMAItemRepository;

/**
 * Return Controller - Frontend-Actions für Kunden-Retouren
 */
class ReturnController
{
    private ReturnRequestService $returnService;
    private NotificationService $notificationService;
    private ShippingLabelService $labelService;
    private RMARepository $rmaRepo;
    private RMAItemRepository $itemRepo;
    private JTLSmarty $smarty;
    
    public function __construct()
    {
        $this->returnService = new ReturnRequestService();
        $this->notificationService = new NotificationService();
        $this->labelService = new ShippingLabelService();
        $this->rmaRepo = new RMARepository();
        $this->itemRepo = new RMAItemRepository();
        $this->smarty = Shop::Smarty();
    }
    
    /**
     * Action: Startseite (Bestellnummer + E-Mail Formular)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->smarty->assign('pageTitle', 'Retoure anmelden');
        
        return $this->render('return_form.tpl', $response);
    }
    
    /**
     * Action: Bestellung validieren (Ajax)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionValidateOrder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $orderNo = $data['orderNo'] ?? '';
        $email = $data['email'] ?? '';
        
        $result = $this->returnService->validateOrderAccess($orderNo, $email);
        
        if ($result['valid']) {
            // Session speichern für nächsten Schritt
            $_SESSION['rma_order_data'] = [
                'orderID' => $result['orderID'],
                'customerID' => $result['customerID'],
                'orderNo' => $orderNo,
                'email' => $email
            ];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'redirect' => Shop::getURL() . '/plugin/customer_returns/select-products'
            ]));
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $result['error']
            ]));
        }
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Action: Produktauswahl
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionSelectProducts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Session-Check
        if (!isset($_SESSION['rma_order_data'])) {
            return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns');
        }
        
        $orderData = $_SESSION['rma_order_data'];
        
        // Retournierbare Produkte laden
        $productsData = $this->returnService->getReturnableProducts($orderData['orderID']);
        
        // Retourengründe laden
        $reasons = $this->returnService->getReturnReasons('GER'); // TODO: Spracherkennung
        
        $this->smarty->assign('pageTitle', 'Artikel auswählen');
        $this->smarty->assign('orderNo', $orderData['orderNo']);
        $this->smarty->assign('products', $productsData['products']);
        $this->smarty->assign('totalValue', $productsData['totalValue']);
        $this->smarty->assign('returnReasons', $reasons);
        
        return $this->render('return_products.tpl', $response);
    }
    
    /**
     * Action: Zusammenfassung
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Session-Check
        if (!isset($_SESSION['rma_order_data'])) {
            return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns');
        }
        
        $data = $request->getParsedBody();
        $selectedItems = $data['items'] ?? [];
        
        if (empty($selectedItems)) {
            return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns/select-products');
        }
        
        // In Session speichern
        $_SESSION['rma_selected_items'] = $selectedItems;
        
        // Produktdaten laden
        $productsData = $this->returnService->getReturnableProducts($_SESSION['rma_order_data']['orderID']);
        $reasons = $this->returnService->getReturnReasons('GER');
        
        // Selected Items mit Produktdaten anreichern
        $summaryItems = [];
        foreach ($selectedItems as $item) {
            $productID = $item['productID'];
            $product = array_filter($productsData['products'], fn($p) => $p['productID'] == $productID);
            $product = reset($product);
            
            if ($product) {
                $reason = array_filter($reasons, fn($r) => $r['id'] == $item['reasonID']);
                $reason = reset($reason);
                
                $summaryItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'reason' => $reason['reason'] ?? '',
                    'comment' => $item['comment'] ?? ''
                ];
            }
        }
        
        $this->smarty->assign('pageTitle', 'Zusammenfassung');
        $this->smarty->assign('orderNo', $_SESSION['rma_order_data']['orderNo']);
        $this->smarty->assign('summaryItems', $summaryItems);
        
        return $this->render('return_summary.tpl', $response);
    }
    
    /**
     * Action: Retoure abschicken
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionSubmit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Session-Check
        if (!isset($_SESSION['rma_order_data']) || !isset($_SESSION['rma_selected_items'])) {
            return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns');
        }
        
        try {
            $orderData = $_SESSION['rma_order_data'];
            $items = $_SESSION['rma_selected_items'];
            
            // Retoure erstellen
            $rma = $this->returnService->createReturnRequest([
                'orderID' => $orderData['orderID'],
                'customerID' => $orderData['customerID'],
                'items' => $items
            ]);
            
            // Bestätigungs-E-Mail senden
            $this->notificationService->sendReturnConfirmation($rma->getId());
            
            // Optional: Versandlabel erstellen
            $autoCreateLabel = Shop::Container()->getConfigService()->get(
                'jtl_customer_returns_auto_create_label',
                false
            );
            
            if ($autoCreateLabel) {
                $this->labelService->createLabel($rma->getId());
            }
            
            // Session aufräumen
            unset($_SESSION['rma_order_data']);
            unset($_SESSION['rma_selected_items']);
            
            // RMA-Nr in Session für Bestätigungsseite
            $_SESSION['rma_confirmation'] = [
                'rmaNr' => $rma->getRmaNr(),
                'rmaID' => $rma->getId()
            ];
            
            return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns/confirmation');
            
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error(
                "Retouren-Erstellung fehlgeschlagen: {$e->getMessage()}"
            );
            
            $this->smarty->assign('error', 'Fehler bei der Retouren-Erstellung. Bitte versuchen Sie es später erneut.');
            return $this->render('return_summary.tpl', $response);
        }
    }
    
    /**
     * Action: Bestätigungsseite
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionConfirmation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Session-Check
        if (!isset($_SESSION['rma_confirmation'])) {
            return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns');
        }
        
        $confirmationData = $_SESSION['rma_confirmation'];
        $rma = $this->rmaRepo->getById($confirmationData['rmaID']);
        
        $this->smarty->assign('pageTitle', 'Retoure erfolgreich angelegt');
        $this->smarty->assign('rmaNr', $confirmationData['rmaNr']);
        $this->smarty->assign('rma', $rma);
        $this->smarty->assign('hasLabel', $rma && $rma->getLabelPath() !== null);
        
        // Session aufräumen
        unset($_SESSION['rma_confirmation']);
        
        return $this->render('return_confirmation.tpl', $response);
    }
    
    /**
     * Action: Meine Retouren (Kundenaccount)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionMyReturns(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Login-Check
        $customer = Shop::Container()->getCustomerService()->getLoggedInCustomer();
        
        if (!$customer) {
            return $response->withStatus(302)->withHeader('Location', '/jtl.php?li');
        }
        
        // Retouren des Kunden laden
        $rmas = $this->rmaRepo->getByCustomerID($customer->kKunde);
        
        // Items für jede RMA laden
        $rmasWithItems = [];
        foreach ($rmas as $rma) {
            $items = $this->itemRepo->getByRmaID($rma->getId());
            $rmasWithItems[] = [
                'rma' => $rma,
                'items' => $items,
                'itemCount' => count($items)
            ];
        }
        
        $this->smarty->assign('pageTitle', 'Meine Retouren');
        $this->smarty->assign('rmas', $rmasWithItems);
        
        return $this->render('my_returns.tpl', $response);
    }
    
    /**
     * Action: Retoure für spezifische Bestellung anlegen (eingeloggte Kunden)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function actionCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Login-Check
        $customer = Shop::Container()->getCustomerService()->getLoggedInCustomer();
        
        if (!$customer) {
            return $response->withStatus(302)->withHeader('Location', '/jtl.php?li');
        }
        
        $params = $request->getQueryParams();
        $orderID = (int)($params['orderID'] ?? 0);
        
        if (!$orderID) {
            return $response->withStatus(302)->withHeader('Location', '/kundenkonto');
        }
        
        // Prüfen ob Bestellung zum Kunden gehört
        $db = Shop::Container()->getDB();
        $order = $db->select('tbestellung', 'kBestellung', $orderID);
        
        if (!$order || (int)$order->kKunde !== $customer->kKunde) {
            $this->smarty->assign('error', 'Bestellung nicht gefunden oder Sie haben keine Berechtigung.');
            return $this->render('error.tpl', $response);
        }
        
        // Prüfen ob bereits RMA existiert
        $existingRMA = $this->rmaRepo->getByOrderID($orderID);
        
        if ($existingRMA) {
            // Weiterleitung zur RMA-Detail-Seite
            return $response->withStatus(302)->withHeader(
                'Location', 
                '/plugin/customer_returns/detail/' . $existingRMA->getId()
            );
        }
        
        // Session mit Bestelldaten vorbelegen
        $_SESSION['rma_order_data'] = [
            'orderID' => $orderID,
            'customerID' => $customer->kKunde,
            'orderNo' => $order->cBestellNr,
            'email' => $customer->cMail
        ];
        
        // Direkt zur Produktauswahl
        return $response->withStatus(302)->withHeader('Location', '/plugin/customer_returns/select-products');
    }
    
    /**
     * Action: RMA-Detail anzeigen
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     */
    public function actionDetail(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        // Login-Check
        $customer = Shop::Container()->getCustomerService()->getLoggedInCustomer();
        
        if (!$customer) {
            return $response->withStatus(302)->withHeader('Location', '/jtl.php?li');
        }
        
        $rmaID = (int)($args['id'] ?? 0);
        
        if (!$rmaID) {
            return $response->withStatus(302)->withHeader('Location', '/kundenkonto/retouren');
        }
        
        // RMA laden
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma || (int)$rma->getCustomerID() !== $customer->kKunde) {
            $this->smarty->assign('error', 'Retoure nicht gefunden oder Sie haben keine Berechtigung.');
            return $this->render('error.tpl', $response);
        }
        
        // Items laden
        $items = $this->itemRepo->getByRmaID($rmaID);
        
        // Bestellung laden
        $db = Shop::Container()->getDB();
        $order = $db->select('tbestellung', 'kBestellung', $rma->getOrderID());
        
        $this->smarty->assign('pageTitle', 'Retoure ' . $rma->getRmaNr());
        $this->smarty->assign('rma', $rma);
        $this->smarty->assign('items', $items);
        $this->smarty->assign('order', $order);
        $this->smarty->assign('hasLabel', $rma->getLabelPath() !== null);
        
        return $this->render('return_detail.tpl', $response);
    }
    
    /**
     * Widget: Retoure-Button für Bestelldetails
     * (Wird vom Bootstrap via Smarty-Plugin aufgerufen)
     *
     * @param int $orderID
     * @return array
     */
    public function getReturnButtonData(int $orderID): array
    {
        $customer = Shop::Container()->getCustomerService()->getLoggedInCustomer();
        
        if (!$customer) {
            return ['isReturnable' => false];
        }
        
        // Prüfen ob Bestellung retournierbar ist
        $db = Shop::Container()->getDB();
        $order = $db->select('tbestellung', 'kBestellung', $orderID);
        
        if (!$order || (int)$order->kKunde !== $customer->kKunde) {
            return ['isReturnable' => false];
        }
        
        // Retourenfrist prüfen
        $config = Shop::Container()->getConfigService();
        $returnPeriodDays = (int)$config->get('jtl_customer_returns_return_period_days', 14);
        
        $orderDate = strtotime($order->dErstellt);
        $returnDeadline = strtotime("+{$returnPeriodDays} days", $orderDate);
        $isReturnable = time() <= $returnDeadline;
        
        // Prüfen ob bereits RMA existiert
        $existingRMA = $this->rmaRepo->getByOrderID($orderID);
        
        return [
            'order' => $order,
            'isReturnable' => $isReturnable,
            'returnPeriodDays' => $returnPeriodDays,
            'existingRMA' => $existingRMA
        ];
    }
    
    /**
     * Template rendern
     *
     * @param string $template
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function render(string $template, ResponseInterface $response): ResponseInterface
    {
        $templatePath = PFAD_ROOT . PFAD_PLUGIN . 'jtl_customer_returns/frontend/template/' . $template;
        
        $html = $this->smarty->fetch($templatePath);
        
        $response->getBody()->write($html);
        
        return $response->withHeader('Content-Type', 'text/html');
    }
}
