<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects;

/**
 * RMA History Domain Object
 * 
 * Event-Sourcing Log für RMA-Historie
 */
class RMAHistoryDomainObject
{
    /** @var int|null */
    private ?int $id = null;
    
    /** @var int */
    private int $rmaID = 0;
    
    /** @var string */
    private string $event = '';
    
    /** @var array<string, mixed>|null */
    private ?array $eventData = null;
    
    /** @var string */
    private string $createdAt = '';
    
    /** @var int|null */
    private ?int $createdBy = null;
    
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
    
    public function getEvent(): string
    {
        return $this->event;
    }
    
    public function setEvent(string $event): self
    {
        $this->event = $event;
        return $this;
    }
    
    /**
     * @return array<string, mixed>|null
     */
    public function getEventData(): ?array
    {
        return $this->eventData;
    }
    
    /**
     * @param array<string, mixed>|null $eventData
     * @return self
     */
    public function setEventData(?array $eventData): self
    {
        $this->eventData = $eventData;
        return $this;
    }
    
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }
    
    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
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
            'event' => $this->event,
            'eventData' => $this->eventData !== null ? json_encode($this->eventData) : null,
            'createdAt' => $this->createdAt,
            'createdBy' => $this->createdBy
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
        $obj->setEvent($data['event'] ?? '');
        
        // JSON decode eventData
        $eventData = null;
        if (isset($data['eventData']) && is_string($data['eventData'])) {
            $eventData = json_decode($data['eventData'], true);
        } elseif (isset($data['eventData']) && is_array($data['eventData'])) {
            $eventData = $data['eventData'];
        }
        $obj->setEventData($eventData);
        
        $obj->setCreatedAt($data['createdAt'] ?? date('Y-m-d H:i:s'));
        $obj->setCreatedBy($data['createdBy'] ?? null);
        
        return $obj;
    }
}
