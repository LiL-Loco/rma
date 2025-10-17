<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects;

/**
 * RMA Return Address Domain Object
 * 
 * Rücksendeadresse (Kundenadressen)
 */
class RMAReturnAddressDomainObject
{
    /** @var int|null */
    private ?int $id = null;
    
    /** @var int */
    private int $customerID = 0;
    
    /** @var string|null */
    private ?string $salutation = null;
    
    /** @var string */
    private string $firstName = '';
    
    /** @var string */
    private string $lastName = '';
    
    /** @var string */
    private string $street = '';
    
    /** @var string|null */
    private ?string $houseNumber = null;
    
    /** @var string */
    private string $zip = '';
    
    /** @var string */
    private string $city = '';
    
    /** @var string */
    private string $country = 'DE';
    
    /** @var string|null */
    private ?string $phone = null;
    
    /** @var string */
    private string $createDate = '';
    
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
    
    public function getCustomerID(): int
    {
        return $this->customerID;
    }
    
    public function setCustomerID(int $customerID): self
    {
        $this->customerID = $customerID;
        return $this;
    }
    
    public function getSalutation(): ?string
    {
        return $this->salutation;
    }
    
    public function setSalutation(?string $salutation): self
    {
        $this->salutation = $salutation;
        return $this;
    }
    
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }
    
    public function getLastName(): string
    {
        return $this->lastName;
    }
    
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }
    
    public function getStreet(): string
    {
        return $this->street;
    }
    
    public function setStreet(string $street): self
    {
        $this->street = $street;
        return $this;
    }
    
    public function getHouseNumber(): ?string
    {
        return $this->houseNumber;
    }
    
    public function setHouseNumber(?string $houseNumber): self
    {
        $this->houseNumber = $houseNumber;
        return $this;
    }
    
    public function getZip(): string
    {
        return $this->zip;
    }
    
    public function setZip(string $zip): self
    {
        $this->zip = $zip;
        return $this;
    }
    
    public function getCity(): string
    {
        return $this->city;
    }
    
    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }
    
    public function getCountry(): string
    {
        return $this->country;
    }
    
    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }
    
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
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
     * Formatierte Adresse als String
     *
     * @return string
     */
    public function getFormattedAddress(): string
    {
        $parts = [];
        
        if ($this->salutation) {
            $parts[] = $this->salutation;
        }
        
        $parts[] = $this->firstName . ' ' . $this->lastName;
        $parts[] = $this->street . ($this->houseNumber ? ' ' . $this->houseNumber : '');
        $parts[] = $this->zip . ' ' . $this->city;
        $parts[] = $this->country;
        
        if ($this->phone) {
            $parts[] = 'Tel: ' . $this->phone;
        }
        
        return implode("\n", $parts);
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
            'customerID' => $this->customerID,
            'salutation' => $this->salutation,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
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
        $obj->setCustomerID((int)($data['customerID'] ?? 0));
        $obj->setSalutation($data['salutation'] ?? null);
        $obj->setFirstName($data['firstName'] ?? '');
        $obj->setLastName($data['lastName'] ?? '');
        $obj->setStreet($data['street'] ?? '');
        $obj->setHouseNumber($data['houseNumber'] ?? null);
        $obj->setZip($data['zip'] ?? '');
        $obj->setCity($data['city'] ?? '');
        $obj->setCountry($data['country'] ?? 'DE');
        $obj->setPhone($data['phone'] ?? null);
        $obj->setCreateDate($data['createDate'] ?? date('Y-m-d H:i:s'));
        
        return $obj;
    }
}
