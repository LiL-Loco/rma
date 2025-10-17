<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects;

/**
 * RMA Domain Object (Return Merchandise Authorization)
 * 
 * Hauptentität für Retouren
 */
class RMADomainObject
{
    /** @var int|null */
    private ?int $id = null;
    
    /** @var string */
    private string $rmaNr = '';
    
    /** @var int */
    private int $orderID = 0;
    
    /** @var int|null */
    private ?int $customerID = null;
    
    /** @var int Status: 0=OPEN, 1=IN_PROGRESS, 2=ACCEPTED, 3=COMPLETED, 4=REJECTED */
    private int $status = 0;
    
    /** @var float */
    private float $totalGross = 0.00;
    
    /** @var int|null */
    private ?int $returnAddressID = null;
    
    /** @var int|null */
    private ?int $wawiID = null;
    
    /** @var bool */
    private bool $synced = false;
    
    /** @var string|null */
    private ?string $labelPath = null;
    
    /** @var string */
    private string $createDate = '';
    
    /** @var string */
    private string $updateDate = '';
    
    /** @var string|null */
    private ?string $lastSyncDate = null;
    
    /** @var array<RMAItemDomainObject> Lazy-loaded */
    private ?array $items = null;
    
    // Status-Konstanten
    public const STATUS_OPEN = 0;
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_ACCEPTED = 2;
    public const STATUS_COMPLETED = 3;
    public const STATUS_REJECTED = 4;
    
    /**
     * RMA-Nummer generieren
     *
     * @return string Format: RMA-YYYY-NNNNN
     */
    public static function generateRmaNr(): string
    {
        $year = date('Y');
        $random = str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        return sprintf('RMA-%s-%s', $year, $random);
    }
    
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
        if ($status < 0 || $status > 4) {
            throw new \InvalidArgumentException('Invalid status: ' . $status);
        }
        
        $this->status = $status;
        return $this;
    }
    
    public function getStatusText(): string
    {
        $statusTexts = [
            self::STATUS_OPEN => 'Offen',
            self::STATUS_IN_PROGRESS => 'In Bearbeitung',
            self::STATUS_ACCEPTED => 'Angenommen',
            self::STATUS_COMPLETED => 'Abgeschlossen',
            self::STATUS_REJECTED => 'Abgelehnt'
        ];
        
        return $statusTexts[$this->status] ?? 'Unbekannt';
    }
    
    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_OPEN => 'primary',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_COMPLETED => 'secondary',
            self::STATUS_REJECTED => 'danger'
        ];
        
        return $colors[$this->status] ?? 'secondary';
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
    
    public function getReturnAddressID(): ?int
    {
        return $this->returnAddressID;
    }
    
    public function setReturnAddressID(?int $returnAddressID): self
    {
        $this->returnAddressID = $returnAddressID;
        return $this;
    }
    
    public function getWawiID(): ?int
    {
        return $this->wawiID;
    }
    
    public function setWawiID(?int $wawiID): self
    {
        $this->wawiID = $wawiID;
        return $this;
    }
    
    public function isSynced(): bool
    {
        return $this->synced;
    }
    
    public function setSynced(bool $synced): self
    {
        $this->synced = $synced;
        return $this;
    }
    
    public function getLabelPath(): ?string
    {
        return $this->labelPath;
    }
    
    public function setLabelPath(?string $labelPath): self
    {
        $this->labelPath = $labelPath;
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
    
    public function getUpdateDate(): string
    {
        return $this->updateDate;
    }
    
    public function setUpdateDate(string $updateDate): self
    {
        $this->updateDate = $updateDate;
        return $this;
    }
    
    public function getLastSyncDate(): ?string
    {
        return $this->lastSyncDate;
    }
    
    public function setLastSyncDate(?string $lastSyncDate): self
    {
        $this->lastSyncDate = $lastSyncDate;
        return $this;
    }
    
    /**
     * Items setzen (Lazy Loading)
     *
     * @param array<RMAItemDomainObject> $items
     * @return self
     */
    public function setItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }
    
    /**
     * Items abrufen
     *
     * @return array<RMAItemDomainObject>
     */
    public function getItems(): array
    {
        return $this->items ?? [];
    }
    
    /**
     * Einzelnes Item hinzufügen
     *
     * @param RMAItemDomainObject $item
     * @return self
     */
    public function addItem(RMAItemDomainObject $item): self
    {
        if ($this->items === null) {
            $this->items = [];
        }
        
        $this->items[] = $item;
        return $this;
    }
    
    /**
     * Anzahl Items
     *
     * @return int
     */
    public function getItemsCount(): int
    {
        return count($this->items ?? []);
    }
    
    /**
     * Zu Array konvertieren (für DB-Insert/Update)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rmaNr' => $this->rmaNr,
            'orderID' => $this->orderID,
            'customerID' => $this->customerID,
            'status' => $this->status,
            'totalGross' => $this->totalGross,
            'returnAddressID' => $this->returnAddressID,
            'wawiID' => $this->wawiID,
            'synced' => (int)$this->synced,
            'labelPath' => $this->labelPath,
            'createDate' => $this->createDate,
            'updateDate' => $this->updateDate,
            'lastSyncDate' => $this->lastSyncDate
        ];
    }
    
    /**
     * Aus Array erstellen (für DB-Select)
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $obj = new self();
        
        $obj->setId($data['id'] ?? null);
        $obj->setRmaNr($data['rmaNr'] ?? '');
        $obj->setOrderID((int)($data['orderID'] ?? 0));
        $obj->setCustomerID($data['customerID'] ? (int)$data['customerID'] : null);
        $obj->setStatus((int)($data['status'] ?? 0));
        $obj->setTotalGross((float)($data['totalGross'] ?? 0.00));
        $obj->setReturnAddressID($data['returnAddressID'] ? (int)$data['returnAddressID'] : null);
        $obj->setWawiID($data['wawiID'] ? (int)$data['wawiID'] : null);
        $obj->setSynced((bool)($data['synced'] ?? false));
        $obj->setLabelPath($data['labelPath'] ?? null);
        $obj->setCreateDate($data['createDate'] ?? date('Y-m-d H:i:s'));
        $obj->setUpdateDate($data['updateDate'] ?? date('Y-m-d H:i:s'));
        $obj->setLastSyncDate($data['lastSyncDate'] ?? null);
        
        return $obj;
    }
}
