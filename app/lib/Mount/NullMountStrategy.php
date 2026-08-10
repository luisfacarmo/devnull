<?php

declare(strict_types=1);

namespace OCA\DevNull\Mount;

use OCA\DevNull\Capability\MountResult;
use OCA\DevNull\Capability\MountStrategyInterface;
use OCA\DevNull\Capability\UnmountResult;

/**
 * Null mount strategy — used when no real strategy is available.
 * Returns user-friendly errors instead of crashing the app.
 *
 * This allows the app to load and show disks even if mounting
 * is not possible (e.g., udisks2 not installed yet).
 */
class NullMountStrategy implements MountStrategyInterface
{
    private const ERROR_MSG = 'Montagem não disponível. Instale udisks2 no servidor: sudo apt install udisks2';

    public function mount(string $device, string $mountpoint): MountResult
    {
        return MountResult::failure(self::ERROR_MSG);
    }

    public function unmount(string $device): UnmountResult
    {
        return UnmountResult::failure(self::ERROR_MSG);
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
