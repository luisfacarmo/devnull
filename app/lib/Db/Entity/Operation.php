<?php

declare(strict_types=1);

namespace OCA\DevNull\Db\Entity;

use OCP\AppFramework\Db\Entity;

/**
 * Entity: Operation log entry.
 * Table: oc_devnull_operations
 */
class Operation extends Entity
{
    protected ?int $diskId = null;
    protected ?string $userId = null;
    protected ?string $type = null;      // mount, unmount, scan, dedup, classify
    protected ?string $status = null;    // pending, running, completed, failed
    protected ?string $startedAt = null;
    protected ?string $finishedAt = null;
    protected ?string $resultJson = null;
    protected ?string $errorMsg = null;

    public function __construct()
    {
        $this->addType('diskId', 'integer');
    }
}
