<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Helper;

use Plugin\jtl_customer_returns\DomainObjects\RMAItemDomainObject;

/**
 * RMA Items - Helper-Funktionen für Item-Collections
 */
class RMAItems
{
    /**
     * Gesamtmenge aller Items berechnen
     *
     * @param array<RMAItemDomainObject> $items
     * @return int
     */
    public static function getTotalQuantity(array $items): int
    {
        return array_reduce(
            $items,
            fn(int $sum, RMAItemDomainObject $item) => $sum + $item->getQuantity(),
            0
        );
    }
    
    /**
     * Gesamtwert aller Rückerstattungen berechnen
     *
     * @param array<RMAItemDomainObject> $items
     * @return float
     */
    public static function getTotalRefundAmount(array $items): float
    {
        return array_reduce(
            $items,
            fn(float $sum, RMAItemDomainObject $item) => $sum + $item->getRefundAmount(),
            0.0
        );
    }
    
    /**
     * Items nach Status filtern
     *
     * @param array<RMAItemDomainObject> $items
     * @param int $status
     * @return array<RMAItemDomainObject>
     */
    public static function filterByStatus(array $items, int $status): array
    {
        return array_filter(
            $items,
            fn(RMAItemDomainObject $item) => $item->getItemStatus() === $status
        );
    }
    
    /**
     * Items nach Retouren-Grund gruppieren
     *
     * @param array<RMAItemDomainObject> $items
     * @return array<int, array<RMAItemDomainObject>>
     */
    public static function groupByReason(array $items): array
    {
        $grouped = [];
        
        foreach ($items as $item) {
            $reasonID = $item->getReasonID();
            if (!isset($grouped[$reasonID])) {
                $grouped[$reasonID] = [];
            }
            $grouped[$reasonID][] = $item;
        }
        
        return $grouped;
    }
    
    /**
     * Prüfen, ob alle Items akzeptiert wurden
     *
     * @param array<RMAItemDomainObject> $items
     * @return bool
     */
    public static function allAccepted(array $items): bool
    {
        return !empty($items) && array_reduce(
            $items,
            fn(bool $acc, RMAItemDomainObject $item) => 
                $acc && $item->getItemStatus() === RMAItemDomainObject::STATUS_ACCEPTED,
            true
        );
    }
    
    /**
     * Prüfen, ob alle Items abgelehnt wurden
     *
     * @param array<RMAItemDomainObject> $items
     * @return bool
     */
    public static function allRejected(array $items): bool
    {
        return !empty($items) && array_reduce(
            $items,
            fn(bool $acc, RMAItemDomainObject $item) => 
                $acc && $item->getItemStatus() === RMAItemDomainObject::STATUS_REJECTED,
            true
        );
    }
    
    /**
     * Prüfen, ob alle Items erstattet wurden
     *
     * @param array<RMAItemDomainObject> $items
     * @return bool
     */
    public static function allRefunded(array $items): bool
    {
        return !empty($items) && array_reduce(
            $items,
            fn(bool $acc, RMAItemDomainObject $item) => 
                $acc && $item->getItemStatus() === RMAItemDomainObject::STATUS_REFUNDED,
            true
        );
    }
    
    /**
     * Items für Dropdown-Auswahl formatieren
     *
     * @param array<RMAItemDomainObject> $items
     * @return array<array{id: int, label: string, quantity: int}>
     */
    public static function toSelectOptions(array $items): array
    {
        return array_map(
            fn(RMAItemDomainObject $item) => [
                'id' => $item->getId(),
                'label' => sprintf(
                    'Artikel #%d (Menge: %d)',
                    $item->getProductID(),
                    $item->getQuantity()
                ),
                'quantity' => $item->getQuantity()
            ],
            $items
        );
    }
    
    /**
     * Status-Statistik berechnen
     *
     * @param array<RMAItemDomainObject> $items
     * @return array{pending: int, accepted: int, rejected: int, refunded: int}
     */
    public static function getStatusStats(array $items): array
    {
        $stats = [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'refunded' => 0
        ];
        
        foreach ($items as $item) {
            switch ($item->getItemStatus()) {
                case RMAItemDomainObject::STATUS_PENDING:
                    $stats['pending']++;
                    break;
                case RMAItemDomainObject::STATUS_ACCEPTED:
                    $stats['accepted']++;
                    break;
                case RMAItemDomainObject::STATUS_REJECTED:
                    $stats['rejected']++;
                    break;
                case RMAItemDomainObject::STATUS_REFUNDED:
                    $stats['refunded']++;
                    break;
            }
        }
        
        return $stats;
    }
}
