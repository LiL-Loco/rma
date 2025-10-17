<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Services;

use Plugin\jtl_customer_returns\DomainObjects\RMAHistoryDomainObject;
use Plugin\jtl_customer_returns\Repositories\RMAHistoryRepository;

/**
 * RMA History Service - Event-Logging
 */
class RMAHistoryService
{
    private RMAHistoryRepository $repo;
    
    public function __construct()
    {
        $this->repo = new RMAHistoryRepository();
    }
    
    /**
     * Event hinzufügen
     *
     * @param int $rmaID
     * @param string $event Event-Konstante aus RMAHistoryEvents
     * @param array $eventData Zusatzdaten als Array
     * @param int|null $userID Admin-User ID (optional)
     * @return int historyID
     */
    public function addEvent(int $rmaID, string $event, array $eventData = [], ?int $userID = null): int
    {
        $history = new RMAHistoryDomainObject();
        $history->setRmaID($rmaID);
        $history->setEvent($event);
        $history->setEventData($eventData);
        $history->setUserID($userID);
        $history->setCreatedAt(date('Y-m-d H:i:s'));
        
        return $this->repo->save($history);
    }
    
    /**
     * Historie für RMA laden
     *
     * @param int $rmaID
     * @return array<RMAHistoryDomainObject>
     */
    public function getHistory(int $rmaID): array
    {
        return $this->repo->getByRmaID($rmaID);
    }
    
    /**
     * Letztes Event laden
     *
     * @param int $rmaID
     * @return RMAHistoryDomainObject|null
     */
    public function getLastEvent(int $rmaID): ?RMAHistoryDomainObject
    {
        return $this->repo->getLastEventByRmaID($rmaID);
    }
}
