<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects;

/**
 * RMA Reason Domain Object
 * 
 * Retouren-Gründe (mehrsprachig)
 */
class RMAReasonDomainObject
{
    /** @var int|null */
    private ?int $id = null;
    
    /** @var string */
    private string $ISO = 'GER';
    
    /** @var string */
    private string $reason = '';
    
    /** @var int */
    private int $sortOrder = 0;
    
    /** @var bool */
    private bool $active = true;
    
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
    
    public function getISO(): string
    {
        return $this->ISO;
    }
    
    public function setISO(string $ISO): self
    {
        $this->ISO = $ISO;
        return $this;
    }
    
    public function getReason(): string
    {
        return $this->reason;
    }
    
    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }
    
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
    
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }
    
    public function isActive(): bool
    {
        return $this->active;
    }
    
    public function setActive(bool $active): self
    {
        $this->active = $active;
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
            'ISO' => $this->ISO,
            'reason' => $this->reason,
            'sortOrder' => $this->sortOrder,
            'active' => (int)$this->active
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
        $obj->setISO($data['ISO'] ?? 'GER');
        $obj->setReason($data['reason'] ?? '');
        $obj->setSortOrder((int)($data['sortOrder'] ?? 0));
        $obj->setActive((bool)($data['active'] ?? true));
        
        return $obj;
    }
}
