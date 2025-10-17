<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects\dbeS;

use Plugin\jtl_customer_returns\DomainObjects\RMAItemDomainObject;

/**
 * RMA Item Sync Object für dbeS
 */
class RMAItemSyncObject
{
    /** @var int */
    private int $productID = 0;
    
    /** @var int|null */
    private ?int $variationID = null;
    
    /** @var int */
    private int $quantity = 1;
    
    /** @var int */
    private int $reasonID = 0;
    
    /** @var float */
    private float $refundAmount = 0.00;
    
    /** @var string|null */
    private ?string $comment = null;
    
    // Getter & Setter
    
    public function getProductID(): int
    {
        return $this->productID;
    }
    
    public function setProductID(int $productID): self
    {
        $this->productID = $productID;
        return $this;
    }
    
    public function getVariationID(): ?int
    {
        return $this->variationID;
    }
    
    public function setVariationID(?int $variationID): self
    {
        $this->variationID = $variationID;
        return $this;
    }
    
    public function getQuantity(): int
    {
        return $this->quantity;
    }
    
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }
    
    public function getReasonID(): int
    {
        return $this->reasonID;
    }
    
    public function setReasonID(int $reasonID): self
    {
        $this->reasonID = $reasonID;
        return $this;
    }
    
    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }
    
    public function setRefundAmount(float $refundAmount): self
    {
        $this->refundAmount = $refundAmount;
        return $this;
    }
    
    public function getComment(): ?string
    {
        return $this->comment;
    }
    
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
    
    /**
     * Aus RMAItemDomainObject erstellen
     *
     * @param RMAItemDomainObject $item
     * @return self
     */
    public static function fromDomainObject(RMAItemDomainObject $item): self
    {
        $obj = new self();
        
        $obj->setProductID($item->getProductID());
        $obj->setVariationID($item->getVariationID());
        $obj->setQuantity($item->getQuantity());
        $obj->setReasonID($item->getReasonID());
        $obj->setRefundAmount($item->getRefundAmount());
        $obj->setComment($item->getComment());
        
        return $obj;
    }
    
    /**
     * Zu XML-Fragment konvertieren
     *
     * @return string
     */
    public function toXMLFragment(): string
    {
        $xml = '    <Item>' . "\n";
        $xml .= '      <ProductID>' . $this->productID . '</ProductID>' . "\n";
        $xml .= '      <VariationID>' . ($this->variationID ?? 0) . '</VariationID>' . "\n";
        $xml .= '      <Quantity>' . $this->quantity . '</Quantity>' . "\n";
        $xml .= '      <ReasonID>' . $this->reasonID . '</ReasonID>' . "\n";
        $xml .= '      <RefundAmount>' . number_format($this->refundAmount, 2, '.', '') . '</RefundAmount>' . "\n";
        
        if ($this->comment !== null) {
            $xml .= '      <Comment>' . htmlspecialchars($this->comment, ENT_XML1) . '</Comment>' . "\n";
        }
        
        $xml .= '    </Item>' . "\n";
        
        return $xml;
    }
}
