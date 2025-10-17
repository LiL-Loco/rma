<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects\dbeS;

use JTL\Abstracts\AbstractDbeSObject;

/**
 * Class RMAReasonLocalizationSyncObject
 *
 * @package JTL\RMA\DomainObjects\dbeS
 * @description Container for RMA data imported from WAWI via dbeS
 */
class RMAReasonLocalizationSyncObject extends AbstractDbeSObject
{
    /**
     * @param int    $reasonID
     * @param int    $langID
     * @param string $title
     */
    public function __construct(
        public int $reasonID,
        public int $langID,
        public string $title,
    ) {
        parent::__construct();
    }
}
