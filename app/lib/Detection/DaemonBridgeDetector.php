<?php

declare(strict_types=1);

namespace OCA\DevNull\Detection;

use OCA\DevNull\Capability\DaemonClientInterface;
use OCA\DevNull\Capability\DiskDetectorInterface;
use OCA\DevNull\Capability\DiskInfo;

/**
 * Detects block devices by querying the external daemon.
 * Higher priority — used when daemon is available.
 */
class DaemonBridgeDetector implements DiskDetectorInterface
{
    public function __construct(
        private DaemonClientInterface $daemonClient,
    ) {
    }

    public function listAvailable(): array
    {
        $rawDisks = $this->daemonClient->listDisks();

        return array_map(
            fn(array $d) => new DiskInfo(
                name: $d['name'] ?? '',
                size: $d['size'] ?? 'unknown',
                fstype: $d['fstype'] ?? null,
                label: $d['label'] ?? null,
                mountpoint: $d['mountpoint'] ?? null,
                serial: $d['serial'] ?? null,
                model: $d['model'] ?? null,
            ),
            $rawDisks
        );
    }

    public function isAvailable(): bool
    {
        return $this->daemonClient->isAvailable();
    }

    public function getPriority(): int
    {
        return 50; // High priority — preferred when daemon is online
    }
}
