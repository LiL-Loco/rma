<?php

declare(strict_types=1);

namespace JTL\RMA\Helper;

use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\DomainObjects\RMAItemDomainObject;

/**
 * Class RMAHistoryEventData
 *
 * @package JTL\RMA\Helper
 * @description Defines a structure for handling RMA related event data between the DB and the RMA History Service
 * @since 5.3.0
 */
class RMAHistoryEventData
{
    /**
     * @param string               $eventName
     * @param RMADomainObject|null $originalDO
     * @param RMADomainObject|null $modifiedDO
     * @since 5.3.0
     */
    public function __construct(
        public readonly string $eventName,
        public readonly ?RMADomainObject $originalDO,
        public readonly ?RMADomainObject $modifiedDO
    ) {
    }

    /**
     * @param string $name
     * @return string
     * @since 5.3.0
     */
    public static function mapEventName(string $name): string
    {
        $mapping = [
            RMADomainObject::class . 'Items'              => RMAHistoryEvents::ITEM_MODIFIED->value,
            RMADomainObject::class . 'ReplacementOrderID' => RMAHistoryEvents::REPLACEMENT_ORDER->value,
            RMADomainObject::class . 'Status'             => RMAHistoryEvents::STATUS_CHANGED->value,
            RMADomainObject::class . 'ReturnAddress'      => RMAHistoryEvents::ADDRESS_MODIFIED->value,
            RMADomainObject::class . 'RefundShipping'     => RMAHistoryEvents::REFUND_SHIPPING->value,
            RMADomainObject::class . 'VoucherCredit'      => RMAHistoryEvents::VOUCHER_CREDIT->value,
            RMAItemDomainObject::class . 'ReasonID'       => RMAHistoryEvents::ITEM_MODIFIED->value,
            RMAItemDomainObject::class . 'Quantity'       => RMAHistoryEvents::ITEM_MODIFIED->value
        ];

        return $mapping[$name] ?? '';
    }
}
