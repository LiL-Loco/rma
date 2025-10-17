<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Repositories;

use JTL\DB\DbInterface;
use JTL\Shop;

/**
 * Abstract Base Repository
 * 
 * Basis-Klasse für alle Repositories mit CRUD-Operationen
 */
abstract class AbstractDBRepository
{
    /** @var DbInterface */
    protected DbInterface $db;
    
    /** @var string Tabellenname */
    protected string $table = '';
    
    /** @var string Primary Key */
    protected string $primaryKey = 'id';
    
    public function __construct(?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
    }
    
    /**
     * Eintrag finden per ID
     *
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object
    {
        $result = $this->db->select($this->table, $this->primaryKey, $id);
        
        return $result ?: null;
    }
    
    /**
     * Alle Einträge abrufen
     *
     * @param int $limit
     * @param int $offset
     * @return array<object>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM `{$this->table}` LIMIT :offset, :limit";
        
        return $this->db->getObjects(
            $sql,
            ['offset' => $offset, 'limit' => $limit]
        );
    }
    
    /**
     * Eintrag erstellen
     *
     * @param object $data
     * @return int Insert-ID
     */
    public function insert(object $data): int
    {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Eintrag aktualisieren
     *
     * @param int $id
     * @param object $data
     * @return int Affected Rows
     */
    public function update(int $id, object $data): int
    {
        return $this->db->update($this->table, $this->primaryKey, $id, $data);
    }
    
    /**
     * Eintrag löschen
     *
     * @param int $id
     * @return int Affected Rows
     */
    public function delete(int $id): int
    {
        return $this->db->delete($this->table, $this->primaryKey, $id);
    }
    
    /**
     * Anzahl Einträge
     *
     * @param string|null $where
     * @param array<string, mixed> $params
     * @return int
     */
    public function count(?string $where = null, array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM `{$this->table}`";
        
        if ($where !== null) {
            $sql .= " WHERE {$where}";
        }
        
        $result = $this->db->getSingleObject($sql, $params);
        
        return (int)($result->cnt ?? 0);
    }
    
    /**
     * Abfrage ausführen
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<object>
     */
    protected function query(string $sql, array $params = []): array
    {
        return $this->db->getObjects($sql, $params);
    }
    
    /**
     * Einzelnes Objekt abrufen
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return object|null
     */
    protected function querySingle(string $sql, array $params = []): ?object
    {
        $result = $this->db->getSingleObject($sql, $params);
        
        return $result ?: null;
    }
}
