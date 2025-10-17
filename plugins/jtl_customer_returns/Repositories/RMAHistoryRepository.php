<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Repositories;

use Plugin\jtl_customer_returns\DomainObjects\RMAHistoryDomainObject;

/**
 * RMA History Repository
 */
class RMAHistoryRepository extends AbstractDBRepository
{
    protected string $table = 'rma_history';
    protected string $primaryKey = 'id';
    
    /**
     * History-Event speichern
     *
     * @param RMAHistoryDomainObject $history
     * @return int
     */
    public function save(RMAHistoryDomainObject $history): int
    {
        $data = (object)$history->toArray();
        unset($data->id);
        
        $id = $this->insert($data);
        $history->setId($id);
        
        return $id;
    }
    
    /**
     * Historie per RMA-ID laden
     *
     * @param int $rmaID
     * @return array<RMAHistoryDomainObject>
     */
    public function getByRmaID(int $rmaID): array
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE rmaID = :rmaID 
                ORDER BY createdAt DESC";
        
        $results = $this->query($sql, ['rmaID' => $rmaID]);
        
        return array_map(
            fn($data) => RMAHistoryDomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * Letztes Event per RMA-ID laden
     *
     * @param int $rmaID
     * @return RMAHistoryDomainObject|null
     */
    public function getLastEventByRmaID(int $rmaID): ?RMAHistoryDomainObject
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE rmaID = :rmaID 
                ORDER BY createdAt DESC 
                LIMIT 1";
        
        $data = $this->querySingle($sql, ['rmaID' => $rmaID]);
        
        return $data ? RMAHistoryDomainObject::fromArray((array)$data) : null;
    }
}
