<?php

declare(strict_types=1);

namespace OCA\DevNull\Detection;

use OCA\DevNull\Capability\DiskDetectorInterface;
use OCA\DevNull\Capability\DiskInfo;
use OCA\DevNull\Command\SecureCommandRunner;

/**
 * Detects block devices using lsblk (local execution).
 * Fallback detector that works without the daemon.
 */
class LsblkDetector implements DiskDetectorInterface
{
    public function __construct(
        private SecureCommandRunner $commandRunner,
    ) {
    }

    public function listAvailable(): array
    {
        $output = $this->commandRunner->run(
            'lsblk',
            ['--json', '--output', 'NAME,SIZE,FSTYPE,LABEL,MOUNTPOINT,TYPE,SERIAL,MODEL']
        );

        $data = json_decode($output, true);
        if (!is_array($data) || !isset($data['blockdevices'])) {
            return [];
        }

        $disks = [];
        foreach ($data['blockdevices'] as $device) {
            if (($device['type'] ?? '') !== 'part') {
                continue;
            }
            if (!empty($device['mountpoint'])) {
                continue;
            }
            if (empty($device['fstype'])) {
                continue;
            }

            $disks[] = new DiskInfo(
                name: $device['name'],
                size: $device['size'] ?? 'unknown',
                fstype: $device['fstype'] ?? null,
                label: $device['label'] ?? null,
                mountpoint: $device['mountpoint'] ?? null,
                serial: $device['serial'] ?? null,
                model: $device['model'] ?? null,
            );
        }

        return $disks;
    }

    public function isAvailable(): bool
    {
        try {
            $this->commandRunner->run('lsblk', ['--version']);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function getPriority(): int
    {
        return 10; // Low priority — fallback
    }
}
