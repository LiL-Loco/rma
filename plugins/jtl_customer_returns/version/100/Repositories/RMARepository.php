<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Repositories;

use Plugin\jtl_customer_returns\DomainObjects\RMADomainObject;

/**
 * RMA Repository
 */
class RMARepository extends AbstractDBRepository
{
    protected string $table = 'rma';
    protected string $primaryKey = 'id';
    
    /**
     * RMA speichern (Insert oder Update)
     *
     * @param RMADomainObject $rma
     * @return int ID
     */
    public function save(RMADomainObject $rma): int
    {
        $data = (object)$rma->toArray();
        
        if ($rma->getId()) {
            // Update
            unset($data->id);
            $this->update($rma->getId(), $data);
            return $rma->getId();
        } else {
            // Insert
            unset($data->id);
            $id = $this->insert($data);
            $rma->setId($id);
            return $id;
        }
    }
    
    /**
     * RMA per ID laden
     *
     * @param int $id
     * @return RMADomainObject|null
     */
    public function getById(int $id): ?RMADomainObject
    {
        $data = $this->find($id);
        
        return $data ? RMADomainObject::fromArray((array)$data) : null;
    }
    
    /**
     * RMA per RMA-Nummer laden
     *
     * @param string $rmaNr
     * @return RMADomainObject|null
     */
    public function getByRmaNr(string $rmaNr): ?RMADomainObject
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE rmaNr = :rmaNr LIMIT 1";
        $data = $this->querySingle($sql, ['rmaNr' => $rmaNr]);
        
        return $data ? RMADomainObject::fromArray((array)$data) : null;
    }
    
    /**
     * RMAs per Bestellung laden
     *
     * @param int $orderID
     * @return array<RMADomainObject>
     */
    public function getByOrderID(int $orderID): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE orderID = :orderID ORDER BY createDate DESC";
        $results = $this->query($sql, ['orderID' => $orderID]);
        
        return array_map(
            fn($data) => RMADomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * RMAs per Kunde laden
     *
     * @param int $customerID
     * @param int $limit
     * @return array<RMADomainObject>
     */
    public function getByCustomerID(int $customerID, int $limit = 50): array
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE customerID = :customerID 
                ORDER BY createDate DESC 
                LIMIT :limit";
        
        $results = $this->query($sql, [
            'customerID' => $customerID,
            'limit' => $limit
        ]);
        
        return array_map(
            fn($data) => RMADomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * RMAs per Status laden
     *
     * @param int $status
     * @param int $limit
     * @return array<RMADomainObject>
     */
    public function getByStatus(int $status, int $limit = 100): array
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE status = :status 
                ORDER BY createDate DESC 
                LIMIT :limit";
        
        $results = $this->query($sql, [
            'status' => $status,
            'limit' => $limit
        ]);
        
        return array_map(
            fn($data) => RMADomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * Unsynchronisierte RMAs laden
     *
     * @param int $limit
     * @return array<RMADomainObject>
     */
    public function getUnsynchronized(int $limit = 100): array
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE synced = 0 
                ORDER BY createDate ASC 
                LIMIT :limit";
        
        $results = $this->query($sql, ['limit' => $limit]);
        
        return array_map(
            fn($data) => RMADomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * Anzahl offener RMAs per Kunde
     *
     * @param int $customerID
     * @return int
     */
    public function countOpenByCustomer(int $customerID): int
    {
        return $this->count(
            'customerID = :customerID AND status IN (0, 1)',
            ['customerID' => $customerID]
        );
    }
    
    /**
     * RMA-Nummer eindeutig prüfen
     *
     * @param string $rmaNr
     * @return bool
     */
    public function isRmaNrUnique(string $rmaNr): bool
    {
        return $this->count('rmaNr = :rmaNr', ['rmaNr' => $rmaNr]) === 0;
    }
}
