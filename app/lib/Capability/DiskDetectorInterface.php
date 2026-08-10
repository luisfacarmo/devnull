<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Capability: Disk Detection
 *
 * Responsible for discovering block devices connected to the server.
 * Implementations may use lsblk (local), daemon bridge (remote), or
 * any future detection mechanism.
 *
 * Domain boundary: ONLY detects. Never mounts, scans, or registers.
 */
interface DiskDetectorInterface
{
    /**
     * List available (unmounted) partitions.
     *
     * @return array<int, DiskInfo>
     */
    public function listAvailable(): array;

    /**
     * Check if this detector implementation is functional.
     */
    public function isAvailable(): bool;

    /**
     * Get the priority of this detector (higher = preferred).
     * Used by the factory to select the best available detector.
     */
    public function getPriority(): int;
}
