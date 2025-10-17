<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Repositories;

use Plugin\jtl_customer_returns\DomainObjects\RMAReasonDomainObject;

/**
 * RMA Reason Repository
 */
class RMAReasonRepository extends AbstractDBRepository
{
    protected string $table = 'rma_reasons';
    protected string $primaryKey = 'id';
    
    /**
     * Retouren-Grund speichern
     *
     * @param RMAReasonDomainObject $reason
     * @return int
     */
    public function save(RMAReasonDomainObject $reason): int
    {
        $data = (object)$reason->toArray();
        
        if ($reason->getId()) {
            unset($data->id);
            $this->update($reason->getId(), $data);
            return $reason->getId();
        } else {
            unset($data->id);
            $id = $this->insert($data);
            $reason->setId($id);
            return $id;
        }
    }
    
    /**
     * Gründe per Sprache laden (nur aktive)
     *
     * @param string $ISO
     * @return array<RMAReasonDomainObject>
     */
    public function getByLanguage(string $ISO = 'GER'): array
    {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE ISO = :ISO AND active = 1 
                ORDER BY sortOrder ASC, reason ASC";
        
        $results = $this->query($sql, ['ISO' => $ISO]);
        
        return array_map(
            fn($data) => RMAReasonDomainObject::fromArray((array)$data),
            $results
        );
    }
    
    /**
     * Alle Gründe laden (inkl. inaktive)
     *
     * @param string|null $ISO
     * @return array<RMAReasonDomainObject>
     */
    public function getAll(?string $ISO = null): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        
        if ($ISO !== null) {
            $sql .= " WHERE ISO = :ISO";
            $params['ISO'] = $ISO;
        }
        
        $sql .= " ORDER BY sortOrder ASC, reason ASC";
        
        $results = $this->query($sql, $params);
        
        return array_map(
            fn($data) => RMAReasonDomainObject::fromArray((array)$data),
            $results
        );
    }
}
