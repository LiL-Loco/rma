<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects;

/**
 * RMA Item Domain Object
 * 
 * Repräsentiert eine einzelne Retouren-Position (Line Item)
 */
class RMAItemDomainObject
{
    /** @var int|null */
    private ?int $id = null;
    
    /** @var int */
    private int $rmaID = 0;
    
    /** @var int */
    private int $productID = 0;
    
    /** @var int|null */
    private ?int $variationID = null;
    
    /** @var int */
    private int $quantity = 1;
    
    /** @var int */
    private int $reasonID = 0;
    
    /** @var int Status: 0=PENDING, 1=ACCEPTED, 2=REJECTED, 3=REFUNDED */
    private int $itemStatus = 0;
    
    /** @var float */
    private float $refundAmount = 0.00;
    
    /** @var string|null */
    private ?string $comment = null;
    
    /** @var string */
    private string $createDate = '';
    
    // Status-Konstanten
    public const STATUS_PENDING = 0;
    public const STATUS_ACCEPTED = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_REFUNDED = 3;
    
    // Getter & Setter
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getRmaID(): int
    {
        return $this->rmaID;
    }
    
    public function setRmaID(int $rmaID): self
    {
        $this->rmaID = $rmaID;
        return $this;
    }
    
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
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1');
        }
        
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
    
    public function getItemStatus(): int
    {
        return $this->itemStatus;
    }
    
    public function setItemStatus(int $itemStatus): self
    {
        if ($itemStatus < 0 || $itemStatus > 3) {
            throw new \InvalidArgumentException('Invalid item status: ' . $itemStatus);
        }
        
        $this->itemStatus = $itemStatus;
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
     * Zu Array konvertieren
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rmaID' => $this->rmaID,
            'productID' => $this->productID,
            'variationID' => $this->variationID,
            'quantity' => $this->quantity,
            'reasonID' => $this->reasonID,
            'itemStatus' => $this->itemStatus,
            'refundAmount' => $this->refundAmount,
            'comment' => $this->comment,
            'createDate' => $this->createDate
        ];
    }
    
    /**
     * Aus Array erstellen
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $obj = new self();
        
        $obj->setId($data['id'] ?? null);
        $obj->setRmaID((int)($data['rmaID'] ?? 0));
        $obj->setProductID((int)($data['productID'] ?? 0));
        $obj->setVariationID($data['variationID'] ? (int)$data['variationID'] : null);
        $obj->setQuantity((int)($data['quantity'] ?? 1));
        $obj->setReasonID((int)($data['reasonID'] ?? 0));
        $obj->setItemStatus((int)($data['itemStatus'] ?? 0));
        $obj->setRefundAmount((float)($data['refundAmount'] ?? 0.00));
        $obj->setComment($data['comment'] ?? null);
        $obj->setCreateDate($data['createDate'] ?? date('Y-m-d H:i:s'));
        
        return $obj;
    }
}
