<?php

declare(strict_types=1);

namespace OCA\DevNull\Status;

use OCA\DevNull\Capability\StatusTransportInterface;
use OCA\DevNull\Db\Mapper\MountMapper;
use OCA\DevNull\Db\Mapper\OperationMapper;

/**
 * Status transport via polling.
 * Frontend calls GET /api/v1/status every N seconds.
 * Returns current state: active mounts, running operations.
 */
class PollingTransport implements StatusTransportInterface
{
    public function __construct(
        private MountMapper $mountMapper,
        private OperationMapper $operationMapper,
    ) {
    }

    public function getStatus(string $userId): array
    {
        $activeMounts = $this->mountMapper->findActiveMounts();
        $runningOps = $this->operationMapper->findRunning();

        return [
            'mounts' => array_map(fn($m) => [
                'id' => $m->getId(),
                'disk_id' => $m->getDiskId(),
                'mountpoint' => $m->getMountpoint(),
                'mounted_at' => $m->getMountedAt(),
            ], $activeMounts),
            'operations' => array_map(fn($op) => [
                'id' => $op->getId(),
                'type' => $op->getType(),
                'status' => $op->getStatus(),
                'started_at' => $op->getStartedAt(),
            ], $runningOps),
            'timestamp' => time(),
        ];
    }

    public function getTransportType(): string
    {
        return 'polling';
    }
}
