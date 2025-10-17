<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\DomainObjects\dbeS;

use Plugin\jtl_customer_returns\DomainObjects\RMAReturnAddressDomainObject;

/**
 * RMA Address Sync Object für dbeS
 */
class RMAAddressSyncObject
{
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
    
    // Getter & Setter
    
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
    
    /**
     * Aus RMAReturnAddressDomainObject erstellen
     *
     * @param RMAReturnAddressDomainObject $address
     * @return self
     */
    public static function fromDomainObject(RMAReturnAddressDomainObject $address): self
    {
        $obj = new self();
        
        $obj->setFirstName($address->getFirstName());
        $obj->setLastName($address->getLastName());
        $obj->setStreet($address->getStreet());
        $obj->setHouseNumber($address->getHouseNumber());
        $obj->setZip($address->getZip());
        $obj->setCity($address->getCity());
        $obj->setCountry($address->getCountry());
        $obj->setPhone($address->getPhone());
        
        return $obj;
    }
    
    /**
     * Zu XML-Fragment konvertieren
     *
     * @return string
     */
    public function toXMLFragment(): string
    {
        $xml = '  <Address>' . "\n";
        $xml .= '    <FirstName>' . htmlspecialchars($this->firstName, ENT_XML1) . '</FirstName>' . "\n";
        $xml .= '    <LastName>' . htmlspecialchars($this->lastName, ENT_XML1) . '</LastName>' . "\n";
        $xml .= '    <Street>' . htmlspecialchars($this->street, ENT_XML1) . '</Street>' . "\n";
        
        if ($this->houseNumber !== null) {
            $xml .= '    <HouseNumber>' . htmlspecialchars($this->houseNumber, ENT_XML1) . '</HouseNumber>' . "\n";
        }
        
        $xml .= '    <Zip>' . htmlspecialchars($this->zip, ENT_XML1) . '</Zip>' . "\n";
        $xml .= '    <City>' . htmlspecialchars($this->city, ENT_XML1) . '</City>' . "\n";
        $xml .= '    <Country>' . htmlspecialchars($this->country, ENT_XML1) . '</Country>' . "\n";
        
        if ($this->phone !== null) {
            $xml .= '    <Phone>' . htmlspecialchars($this->phone, ENT_XML1) . '</Phone>' . "\n";
        }
        
        $xml .= '  </Address>' . "\n";
        
        return $xml;
    }
}
