<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects\dbeS;

use Plugin\jtl_customer_returns\DomainObjects\RMADomainObject;

/**
 * RMA Sync Object für dbeS (JTL-Wawi)
 * 
 * Wird für die Synchronisation Shop → Wawi verwendet
 */
class RMASyncObject
{
    /** @var int */
    private int $id = 0;
    
    /** @var string */
    private string $rmaNr = '';
    
    /** @var int */
    private int $orderID = 0;
    
    /** @var int|null */
    private ?int $customerID = null;
    
    /** @var int */
    private int $status = 0;
    
    /** @var float */
    private float $totalGross = 0.00;
    
    /** @var string */
    private string $createDate = '';
    
    /** @var array<RMAItemSyncObject> */
    private array $items = [];
    
    /** @var RMAAddressSyncObject|null */
    private ?RMAAddressSyncObject $address = null;
    
    // Getter & Setter
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getRmaNr(): string
    {
        return $this->rmaNr;
    }
    
    public function setRmaNr(string $rmaNr): self
    {
        $this->rmaNr = $rmaNr;
        return $this;
    }
    
    public function getOrderID(): int
    {
        return $this->orderID;
    }
    
    public function setOrderID(int $orderID): self
    {
        $this->orderID = $orderID;
        return $this;
    }
    
    public function getCustomerID(): ?int
    {
        return $this->customerID;
    }
    
    public function setCustomerID(?int $customerID): self
    {
        $this->customerID = $customerID;
        return $this;
    }
    
    public function getStatus(): int
    {
        return $this->status;
    }
    
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }
    
    public function getTotalGross(): float
    {
        return $this->totalGross;
    }
    
    public function setTotalGross(float $totalGross): self
    {
        $this->totalGross = $totalGross;
        return $this;
    }
    
    public function getCreateDate(): string
    {
        return $this->createDate;
    }
    
    public function setCreateDate(string $createDate): self
    {
        $this->createDate = $createDate;
        return $this;
    }
    
    /**
     * @return array<RMAItemSyncObject>
     */
    public function getItems(): array
    {
        return $this->items;
    }
    
    /**
     * @param array<RMAItemSyncObject> $items
     * @return self
     */
    public function setItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }
    
    public function addItem(RMAItemSyncObject $item): self
    {
        $this->items[] = $item;
        return $this;
    }
    
    public function getAddress(): ?RMAAddressSyncObject
    {
        return $this->address;
    }
    
    public function setAddress(?RMAAddressSyncObject $address): self
    {
        $this->address = $address;
        return $this;
    }
    
    /**
     * Aus RMADomainObject erstellen
     *
     * @param RMADomainObject $rma
     * @return self
     */
    public static function fromDomainObject(RMADomainObject $rma): self
    {
        $obj = new self();
        
        $obj->setId($rma->getId() ?? 0);
        $obj->setRmaNr($rma->getRmaNr());
        $obj->setOrderID($rma->getOrderID());
        $obj->setCustomerID($rma->getCustomerID());
        $obj->setStatus($rma->getStatus());
        $obj->setTotalGross($rma->getTotalGross());
        $obj->setCreateDate($rma->getCreateDate());
        
        return $obj;
    }
    
    /**
     * Zu XML konvertieren (für dbeS)
     *
     * @return string
     */
    public function toXML(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<RMA>' . "\n";
        $xml .= '  <ID>' . $this->id . '</ID>' . "\n";
        $xml .= '  <RMANr>' . htmlspecialchars($this->rmaNr, ENT_XML1) . '</RMANr>' . "\n";
        $xml .= '  <OrderID>' . $this->orderID . '</OrderID>' . "\n";
        $xml .= '  <CustomerID>' . ($this->customerID ?? 0) . '</CustomerID>' . "\n";
        $xml .= '  <Status>' . $this->status . '</Status>' . "\n";
        $xml .= '  <TotalGross>' . number_format($this->totalGross, 2, '.', '') . '</TotalGross>' . "\n";
        $xml .= '  <CreateDate>' . htmlspecialchars($this->createDate, ENT_XML1) . '</CreateDate>' . "\n";
        
        // Items
        if (!empty($this->items)) {
            $xml .= '  <Items>' . "\n";
            foreach ($this->items as $item) {
                $xml .= $item->toXMLFragment();
            }
            $xml .= '  </Items>' . "\n";
        }
        
        // Address
        if ($this->address !== null) {
            $xml .= $this->address->toXMLFragment();
        }
        
        $xml .= '</RMA>';
        
        return $xml;
    }
}
