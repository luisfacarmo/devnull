<?php

declare(strict_types=1);

namespace OCA\DevNull\Db\Entity;

use OCP\AppFramework\Db\Entity;

/**
 * Entity: Known disk (identified by serial).
 * Table: oc_devnull_disks
 */
class Disk extends Entity
{
    protected ?string $serial = null;
    protected ?string $label = null;
    protected ?string $model = null;
    protected ?string $fstype = null;
    protected ?int $sizeBytes = null;
    protected ?string $firstSeen = null;
    protected ?string $lastSeen = null;

    public function __construct()
    {
        $this->addType('sizeBytes', 'integer');
    }
}
