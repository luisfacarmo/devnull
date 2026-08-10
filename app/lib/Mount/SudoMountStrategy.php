<?php

declare(strict_types=1);

namespace OCA\DevNull\Mount;

use OCA\DevNull\Capability\MountResult;
use OCA\DevNull\Capability\MountStrategyInterface;
use OCA\DevNull\Capability\UnmountResult;
use OCA\DevNull\Command\SecureCommandRunner;

/**
 * Mount strategy using sudo mount (requires sudoers rule).
 * Fallback for environments without udisks2.
 */
class SudoMountStrategy implements MountStrategyInterface
{
    public function __construct(
        private SecureCommandRunner $commandRunner,
    ) {
    }

    public function mount(string $device, string $mountpoint): MountResult
    {
        $devicePath = '/dev/' . $device;

        try {
            $this->commandRunner->run('sudo', [
                'mount', $devicePath, $mountpoint,
                '-o', 'uid=33,gid=33,nofail',
            ]);

            return MountResult::success($mountpoint);
        } catch (\RuntimeException $e) {
            return MountResult::failure('sudo mount failed: ' . $e->getMessage());
        }
    }

    public function unmount(string $device): UnmountResult
    {
        $devicePath = '/dev/' . $device;

        try {
            $this->commandRunner->run('sudo', ['umount', $devicePath]);
            return UnmountResult::success();
        } catch (\RuntimeException $e) {
            return UnmountResult::failure('sudo umount failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            $this->commandRunner->run('sudo', ['-n', 'mount', '--version']);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function getPriority(): int
    {
        return 20; // Lower priority: requires root config
    }
}
