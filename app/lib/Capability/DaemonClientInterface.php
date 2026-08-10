<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Capability: Daemon Communication
 *
 * Responsible for bridging the PHP app with the external daemon.
 * The NullDaemonClient implementation provides graceful fallback
 * when the daemon is unavailable.
 *
 * Domain boundary: ONLY communicates with daemon. All business logic
 * remains in the app.
 */
interface DaemonClientInterface
{
    /**
     * Check if the daemon is reachable.
     */
    public function isAvailable(): bool;

    /**
     * Request disk list from daemon.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDisks(): array;

    /**
     * Request daemon to mount a device.
     *
     * @param string $device Device name
     * @return array{success: bool, mountpoint?: string, error?: string}
     */
    public function mount(string $device): array;

    /**
     * Request daemon to unmount a device.
     *
     * @param string $device Device name
     * @return array{success: bool, error?: string}
     */
    public function unmount(string $device): array;

    /**
     * Get daemon health status.
     *
     * @return array{status: string, uptime?: int, version?: string}
     */
    public function health(): array;
}
