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
            $output = $this->commandRunner->run('udisksctl', [
                'mount',
                '-b', $devicePath,
                '--no-user-interaction',
            ]);

            // Parse actual mountpoint from udisksctl stdout
            // Format: "Mounted /dev/sdb1 at /media/www-data/LABEL."
            $actualMountpoint = $mountpoint; // fallback
            if (preg_match('/at\s+(.+?)\.?\s*$/', $output, $matches)) {
                $actualMountpoint = trim($matches[1]);
            }

            return MountResult::success($actualMountpoint);
        } catch (\RuntimeException $e) {
            // Handle "AlreadyMounted" — not a real failure
            $msg = $e->getMessage();
            if (str_contains($msg, 'AlreadyMounted') || str_contains($msg, 'already mounted')) {
                // Parse mountpoint from error: "...already mounted at `/media/www-data/LABEL'."
                if (preg_match("/mounted at [`'](.+?)['`]/", $msg, $matches)) {
                    return MountResult::success(trim($matches[1]));
                }
                // Fallback: try to get mountpoint from lsblk
                $detected = $this->detectMountpoint($device);
                if ($detected !== null) {
                    return MountResult::success($detected);
                }
            }
            return MountResult::failure('udisksctl mount failed: ' . $msg);
        }
    }

    /**
     * Detect current mountpoint for a device via lsblk.
     */
    private function detectMountpoint(string $device): ?string
    {
        try {
            $output = $this->commandRunner->run('lsblk', [
                '-n', '-o', 'MOUNTPOINTS', '/dev/' . $device,
            ]);
            $mp = trim($output);
            return $mp !== '' ? $mp : null;
        } catch (\RuntimeException) {
            return null;
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
