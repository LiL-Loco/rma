<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Repositories;

use Plugin\jtl_customer_returns\DomainObjects\RMAItemDomainObject;

/**
 * RMA Item Repository
 */
class RMAItemRepository extends AbstractDBRepository
{
    protected string $table = 'rma_items';
    protected string $primaryKey = 'id';
    
    /**
     * RMA-Item speichern
     *
     * @param RMAItemDomainObject $item
     * @return int
     */
    public function save(RMAItemDomainObject $item): int
    {
        $data = (object)$item->toArray();
        
        if ($item->getId()) {
            unset($data->id);
            $this->update($item->getId(), $data);
            return $item->getId();
        } else {
            unset($data->id);
            $id = $this->insert($data);
            $item->setId($id);
            return $id;
        }
    }
    
    /**
     * Items per RMA-ID laden
     *
     * @param int $rmaID
     * @return array<RMAItemDomainObject>
     */
    public function getByRmaID(int $rmaID): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE rmaID = :rmaID ORDER BY id ASC";
        $results = $this->query($sql, ['rmaID' => $rmaID]);
        
        return array_map(
            fn($data) => RMAItemDomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * Alle Items einer RMA löschen
     *
     * @param int $rmaID
     * @return int
     */
    public function deleteByRmaID(int $rmaID): int
    {
        $sql = "DELETE FROM `{$this->table}` WHERE rmaID = :rmaID";
        return $this->db->queryPrepared($sql, ['rmaID' => $rmaID]);
    }
}
