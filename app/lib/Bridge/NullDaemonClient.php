<?php

declare(strict_types=1);

namespace OCA\DevNull\Bridge;

use OCA\DevNull\Capability\DaemonClientInterface;

/**
 * Null implementation of DaemonClient.
 * Used when daemon is not configured or not reachable.
 * Implements P3 (Incremental Evolution): system works without daemon.
 */
class NullDaemonClient implements DaemonClientInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function listDisks(): array
    {
        return [];
    }

    public function mount(string $device): array
    {
        return ['success' => false, 'error' => 'Daemon not available'];
    }

    public function unmount(string $device): array
    {
        return ['success' => false, 'error' => 'Daemon not available'];
    }

    public function health(): array
    {
        return ['status' => 'offline'];
    }
}
