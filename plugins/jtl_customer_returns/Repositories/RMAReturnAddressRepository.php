<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Repositories;

use Plugin\jtl_customer_returns\DomainObjects\RMAReturnAddressDomainObject;

/**
 * RMA Return Address Repository
 */
class RMAReturnAddressRepository extends AbstractDBRepository
{
    protected string $table = 'return_address';
    protected string $primaryKey = 'id';
    
    /**
     * Adresse speichern
     *
     * @param RMAReturnAddressDomainObject $address
     * @return int
     */
    public function save(RMAReturnAddressDomainObject $address): int
    {
        $data = (object)$address->toArray();
        
        if ($address->getId()) {
            unset($data->id);
            $this->update($address->getId(), $data);
            return $address->getId();
        } else {
            unset($data->id);
            $id = $this->insert($data);
            $address->setId($id);
            return $id;
        }
    }
    
    /**
     * Adressen per Kunde laden
     *
     * @param int $customerID
     * @return array<RMAReturnAddressDomainObject>
     */
    public function getByCustomerID(int $customerID): array
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE customerID = :customerID 
                ORDER BY createDate DESC";
        
        $results = $this->query($sql, ['customerID' => $customerID]);
        
        return array_map(
            fn($data) => RMAReturnAddressDomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * Alle Adressen eines Kunden löschen
     *
     * @param int $customerID
     * @return int
     */
    public function deleteByCustomerID(int $customerID): int
    {
        $sql = "DELETE FROM `{$this->table}` WHERE customerID = :customerID";
        return $this->db->queryPrepared($sql, ['customerID' => $customerID]);
    }
}
