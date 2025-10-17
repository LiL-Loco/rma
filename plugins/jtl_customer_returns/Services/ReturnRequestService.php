<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Services;

use JTL\Shop;
use Plugin\jtl_customer_returns\DomainObjects\RMADomainObject;
use Plugin\jtl_customer_returns\DomainObjects\RMAItemDomainObject;
use Plugin\jtl_customer_returns\DomainObjects\RMAReturnAddressDomainObject;
use Plugin\jtl_customer_returns\Repositories\RMARepository;
use Plugin\jtl_customer_returns\Repositories\RMAItemRepository;
use Plugin\jtl_customer_returns\Repositories\RMAReasonRepository;
use Plugin\jtl_customer_returns\Repositories\RMAReturnAddressRepository;
use Plugin\jtl_customer_returns\Helper\RMAHistoryEvents;

/**
 * Return Request Service - Core Business Logic für Retouren
 */
class ReturnRequestService
{
    private RMARepository $rmaRepo;
    private RMAItemRepository $itemRepo;
    private RMAReasonRepository $reasonRepo;
    private RMAReturnAddressRepository $addressRepo;
    private RMAHistoryService $historyService;
    
    public function __construct()
    {
        $this->rmaRepo = new RMARepository();
        $this->itemRepo = new RMAItemRepository();
        $this->reasonRepo = new RMAReasonRepository();
        $this->addressRepo = new RMAReturnAddressRepository();
        $this->historyService = new RMAHistoryService();
    }
    
    /**
     * Bestellung validieren (Bestellnummer + E-Mail)
     *
     * @param string $orderNo
     * @param string $email
     * @return array{valid: bool, orderID?: int, customerID?: int, error?: string}
     */
    public function validateOrderAccess(string $orderNo, string $email): array
    {
        $db = Shop::Container()->getDB();
        
        $order = $db->queryPrepared(
            "SELECT kBestellung, kKunde, cMail, dErstellt 
             FROM tbestellung 
             WHERE cBestellNr = :orderNo 
             LIMIT 1",
            ['orderNo' => $orderNo],
            1
        );
        
        if (!$order) {
            return ['valid' => false, 'error' => 'Bestellung nicht gefunden'];
        }
        
        // E-Mail-Abgleich (case-insensitive)
        if (strtolower($order->cMail) !== strtolower($email)) {
            return ['valid' => false, 'error' => 'E-Mail-Adresse stimmt nicht überein'];
        }
        
        // Retourenzeitraum prüfen
        $returnPeriodDays = (int)Shop::Container()->getConfigService()->get('jtl_customer_returns_return_period_days', 14);
        $orderDate = new \DateTime($order->dErstellt);
        $maxReturnDate = $orderDate->modify("+{$returnPeriodDays} days");
        
        if (new \DateTime() > $maxReturnDate) {
            return ['valid' => false, 'error' => 'Retourenzeitraum abgelaufen'];
        }
        
        return [
            'valid' => true,
            'orderID' => (int)$order->kBestellung,
            'customerID' => (int)$order->kKunde
        ];
    }
    
    /**
     * Retournierbare Produkte laden
     *
     * @param int $orderID
     * @return array{products: array, totalValue: float}
     */
    public function getReturnableProducts(int $orderID): array
    {
        $db = Shop::Container()->getDB();
        
        // Bereits retournierte Mengen ermitteln
        $returnedQty = $db->queryPrepared(
            "SELECT ri.productID, SUM(ri.quantity) AS returnedQty
             FROM rma r
             INNER JOIN rma_items ri ON r.id = ri.rmaID
             WHERE r.orderID = :orderID
             GROUP BY ri.productID",
            ['orderID' => $orderID],
            2
        );
        
        $returned = [];
        foreach ($returnedQty as $row) {
            $returned[(int)$row->productID] = (int)$row->returnedQty;
        }
        
        // Bestellpositionen laden
        $products = $db->queryPrepared(
            "SELECT 
                tw.kWarenkorbPos,
                tw.kArtikel AS productID,
                tw.kArtikelVariation AS variationID,
                tw.cArtNr AS articleNo,
                tw.cName AS productName,
                tw.nAnzahl AS orderedQty,
                tw.fPreis AS price,
                tw.fMwSt AS taxRate,
                a.cVPE AS unit,
                a.cBildpfad AS imagePath
             FROM twarenkorbpos tw
             INNER JOIN tartikel a ON tw.kArtikel = a.kArtikel
             WHERE tw.kWarenkorb = (
                SELECT kWarenkorb FROM tbestellung WHERE kBestellung = :orderID
             )
             AND tw.nPosTyp = 1
             ORDER BY tw.nSort ASC",
            ['orderID' => $orderID],
            2
        );
        
        $returnableProducts = [];
        $totalValue = 0.0;
        
        foreach ($products as $product) {
            $productID = (int)$product->productID;
            $orderedQty = (int)$product->orderedQty;
            $returnedQty = $returned[$productID] ?? 0;
            $availableQty = $orderedQty - $returnedQty;
            
            if ($availableQty > 0) {
                $returnableProducts[] = [
                    'productID' => $productID,
                    'variationID' => (int)$product->variationID,
                    'articleNo' => $product->articleNo,
                    'name' => $product->productName,
                    'orderedQty' => $orderedQty,
                    'returnedQty' => $returnedQty,
                    'availableQty' => $availableQty,
                    'price' => (float)$product->price,
                    'taxRate' => (float)$product->taxRate,
                    'unit' => $product->unit ?? 'Stk.',
                    'imagePath' => $product->imagePath ?? '',
                ];
                
                $totalValue += (float)$product->price * $availableQty;
            }
        }
        
        return [
            'products' => $returnableProducts,
            'totalValue' => $totalValue
        ];
    }
    
    /**
     * Retourengründe laden
     *
     * @param string $ISO
     * @return array
     */
    public function getReturnReasons(string $ISO = 'GER'): array
    {
        $reasons = $this->reasonRepo->getByLanguage($ISO);
        
        return array_map(
            fn($reason) => [
                'id' => $reason->getId(),
                'reason' => $reason->getReason()
            ],
            $reasons
        );
    }
    
    /**
     * Retoure erstellen
     *
     * @param array $data {orderID, customerID, items: [{productID, variationID, quantity, reasonID, comment}], returnAddressID?}
     * @return RMADomainObject
     * @throws \RuntimeException
     */
    public function createReturnRequest(array $data): RMADomainObject
    {
        // Validierung
        if (empty($data['orderID']) || empty($data['customerID']) || empty($data['items'])) {
            throw new \RuntimeException('Ungültige Retouren-Daten');
        }
        
        // RMA-Objekt erstellen
        $rma = new RMADomainObject();
        $rma->setRmaNr($this->generateUniqueRmaNr());
        $rma->setOrderID((int)$data['orderID']);
        $rma->setCustomerID((int)$data['customerID']);
        $rma->setStatus(RMADomainObject::STATUS_OPEN);
        $rma->setSynced(0);
        $rma->setCreateDate(date('Y-m-d H:i:s'));
        
        // Rücksendeadresse
        if (!empty($data['returnAddressID'])) {
            $rma->setReturnAddressID((int)$data['returnAddressID']);
        } else {
            // Standardadresse aus Kundenaccount erstellen
            $addressID = $this->createReturnAddressFromCustomer((int)$data['customerID']);
            $rma->setReturnAddressID($addressID);
        }
        
        // Gesamtwert berechnen
        $totalGross = $this->calculateTotalRefund($data['items']);
        $rma->setTotalGross($totalGross);
        
        // RMA speichern
        $rmaID = $this->rmaRepo->save($rma);
        
        // Items speichern
        foreach ($data['items'] as $itemData) {
            $item = new RMAItemDomainObject();
            $item->setRmaID($rmaID);
            $item->setProductID((int)$itemData['productID']);
            $item->setVariationID((int)($itemData['variationID'] ?? 0));
            $item->setQuantity((int)$itemData['quantity']);
            $item->setReasonID((int)$itemData['reasonID']);
            $item->setItemStatus(RMAItemDomainObject::STATUS_PENDING);
            $item->setComment($itemData['comment'] ?? '');
            
            // Rückerstattungsbetrag ermitteln
            $refundAmount = $this->getProductRefundAmount(
                (int)$data['orderID'],
                (int)$itemData['productID'],
                (int)$itemData['quantity']
            );
            $item->setRefundAmount($refundAmount);
            
            $this->itemRepo->save($item);
        }
        
        // History-Eintrag
        $this->historyService->addEvent(
            $rmaID,
            RMAHistoryEvents::RMA_CREATED,
            ['rmaNr' => $rma->getRmaNr(), 'itemCount' => count($data['items'])]
        );
        
        return $rma;
    }
    
    /**
     * RMA-Status aktualisieren
     *
     * @param int $rmaID
     * @param int $newStatus
     * @param string|null $comment
     * @return void
     */
    public function updateStatus(int $rmaID, int $newStatus, ?string $comment = null): void
    {
        $rma = $this->rmaRepo->getById($rmaID);
        
        if (!$rma) {
            throw new \RuntimeException("RMA #{$rmaID} nicht gefunden");
        }
        
        $oldStatus = $rma->getStatus();
        $rma->setStatus($newStatus);
        $rma->setUpdateDate(date('Y-m-d H:i:s'));
        
        $this->rmaRepo->save($rma);
        
        // History-Eintrag
        $this->historyService->addEvent(
            $rmaID,
            RMAHistoryEvents::STATUS_CHANGED,
            [
                'oldStatus' => $oldStatus,
                'newStatus' => $newStatus,
                'comment' => $comment
            ]
        );
    }
    
    /**
     * Eindeutige RMA-Nummer generieren
     *
     * @return string
     */
    private function generateUniqueRmaNr(): string
    {
        do {
            $rmaNr = RMADomainObject::generateRmaNr();
        } while (!$this->rmaRepo->isRmaNrUnique($rmaNr));
        
        return $rmaNr;
    }
    
    /**
     * Rücksendeadresse aus Kundenaccount erstellen
     *
     * @param int $customerID
     * @return int addressID
     */
    private function createReturnAddressFromCustomer(int $customerID): int
    {
        $db = Shop::Container()->getDB();
        
        $customer = $db->queryPrepared(
            "SELECT cVorname, cNachname, cStrasse, cHausnummer, cPLZ, cOrt, cLand, cMail, cTel
             FROM tkunde
             WHERE kKunde = :customerID",
            ['customerID' => $customerID],
            1
        );
        
        $address = new RMAReturnAddressDomainObject();
        $address->setCustomerID($customerID);
        $address->setFirstName($customer->cVorname ?? '');
        $address->setLastName($customer->cNachname ?? '');
        $address->setStreet($customer->cStrasse ?? '');
        $address->setHouseNumber($customer->cHausnummer ?? '');
        $address->setZip($customer->cPLZ ?? '');
        $address->setCity($customer->cOrt ?? '');
        $address->setCountry($customer->cLand ?? 'DE');
        $address->setEmail($customer->cMail ?? '');
        $address->setPhone($customer->cTel ?? '');
        $address->setCreateDate(date('Y-m-d H:i:s'));
        
        return $this->addressRepo->save($address);
    }
    
    /**
     * Gesamtrückerstattung berechnen
     *
     * @param array $items
     * @return float
     */
    private function calculateTotalRefund(array $items): float
    {
        $total = 0.0;
        
        foreach ($items as $item) {
            $total += $item['refundAmount'] ?? 0.0;
        }
        
        return $total;
    }
    
    /**
     * Rückerstattungsbetrag für Produkt ermitteln
     *
     * @param int $orderID
     * @param int $productID
     * @param int $quantity
     * @return float
     */
    private function getProductRefundAmount(int $orderID, int $productID, int $quantity): float
    {
        $db = Shop::Container()->getDB();
        
        $product = $db->queryPrepared(
            "SELECT fPreis, fMwSt
             FROM twarenkorbpos
             WHERE kWarenkorb = (SELECT kWarenkorb FROM tbestellung WHERE kBestellung = :orderID)
             AND kArtikel = :productID
             LIMIT 1",
            ['orderID' => $orderID, 'productID' => $productID],
            1
        );
        
        if (!$product) {
            return 0.0;
        }
        
        $priceGross = (float)$product->fPreis * (1 + (float)$product->fMwSt / 100);
        
        return round($priceGross * $quantity, 2);
    }
}
