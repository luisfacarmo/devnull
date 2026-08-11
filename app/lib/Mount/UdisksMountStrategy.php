<?php

declare(strict_types=1);

namespace OCA\DevNull\Mount;

use OCA\DevNull\Capability\MountResult;
use OCA\DevNull\Capability\MountStrategyInterface;
use OCA\DevNull\Capability\UnmountResult;
use OCA\DevNull\Command\SecureCommandRunner;

/**
 * Mount strategy using udisks2 (userspace, no root required).
 * Requires polkit rule for www-data.
 */
class UdisksMountStrategy implements MountStrategyInterface
{
    public function __construct(
        private SecureCommandRunner $commandRunner,
    ) {
    }

    public function mount(string $device, string $mountpoint): MountResult
    {
        $devicePath = '/dev/' . $device;

        try {
            $this->commandRunner->run('udisksctl', [
                'mount',
                '-b', $devicePath,
                '--no-user-interaction',
            ]);

            return MountResult::success($mountpoint);
        } catch (\RuntimeException $e) {
            return MountResult::failure('udisksctl mount failed: ' . $e->getMessage());
        }
    }

    public function unmount(string $device): UnmountResult
    {
        $devicePath = '/dev/' . $device;

        try {
            $this->commandRunner->run('udisksctl', [
                'unmount',
                '-b', $devicePath,
                '--no-user-interaction',
                '--force',
            ]);

            return UnmountResult::success();
        } catch (\RuntimeException $e) {
            return UnmountResult::failure('udisksctl unmount falhou: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            // Just check if the binary exists and is executable
            $this->commandRunner->run('udisksctl', ['help']);
            return true;
        } catch (\RuntimeException) {
            // Try alternative: just check binary exists
            return is_executable('/usr/bin/udisksctl');
        }
    }

    public function getPriority(): int
    {
        return 50; // Preferred: no root needed
    }
}
