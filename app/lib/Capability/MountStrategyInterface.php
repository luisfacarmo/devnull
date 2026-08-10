<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Capability: Mount/Unmount
 *
 * Responsible for mounting and unmounting block devices.
 * Implementations may use udisksctl, sudo mount, or daemon bridge.
 *
 * Domain boundary: ONLY mounts/unmounts. Never detects, registers storage,
 * or triggers scans.
 */
interface MountStrategyInterface
{
    /**
     * Mount a device to a target path.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @param string $mountpoint Target mountpoint path
     * @return MountResult
     */
    public function mount(string $device, string $mountpoint): MountResult;

    /**
     * Unmount a device.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @return UnmountResult
     */
    public function unmount(string $device): UnmountResult;

    /**
     * Check if this strategy is available on the current system.
     */
    public function isAvailable(): bool;

    /**
     * Get the priority of this strategy (higher = preferred).
     */
    public function getPriority(): int;
}
