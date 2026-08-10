<?php

declare(strict_types=1);

namespace OCA\DevNull\Db\Entity;

use OCP\AppFramework\Db\Entity;

/**
 * Entity: Active mount record.
 * Table: oc_devnull_mounts
 */
class Mount extends Entity
{
    protected ?int $diskId = null;
    protected ?string $userId = null;
    protected ?int $storageId = null;
    protected ?string $mountpoint = null;
    protected ?string $mountedAt = null;

    public function __construct()
    {
        $this->addType('diskId', 'integer');
        $this->addType('storageId', 'integer');
    }
}
