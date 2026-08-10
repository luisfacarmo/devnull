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
        $this->collectPartitions($data['blockdevices'], $disks);

        return $disks;
    }

    /**
     * Recursively collect partitions from nested lsblk output.
     * Includes both mounted and unmounted external partitions.
     * Excludes system disks (mounted on /, /boot, [SWAP]).
     */
    private function collectPartitions(array $devices, array &$disks): void
    {
        $systemMounts = ['/', '/boot', '/boot/efi', '[SWAP]'];

        foreach ($devices as $device) {
            $type = $device['type'] ?? '';
            $mountpoint = $device['mountpoint'] ?? null;
            $fstype = $device['fstype'] ?? null;

            // Process partitions with a known filesystem
            if ($type === 'part' && !empty($fstype)) {
                // Skip system partitions
                if (in_array($mountpoint, $systemMounts, true)) {
                    // Recurse into children if any
                    if (!empty($device['children'])) {
                        $this->collectPartitions($device['children'], $disks);
                    }
                    continue;
                }

                $disks[] = new DiskInfo(
                    name: $device['name'],
                    size: $device['size'] ?? 'unknown',
                    fstype: $fstype,
                    label: $device['label'] ?? null,
                    mountpoint: $mountpoint,
                    serial: $device['serial'] ?? null,
                    model: $device['model'] ?? null,
                );
            }

            // Recurse into children (disks have partitions as children)
            if (!empty($device['children'])) {
                // Pass parent serial/model to children that lack it
                foreach ($device['children'] as &$child) {
                    if (empty($child['serial']) && !empty($device['serial'])) {
                        $child['serial'] = $device['serial'];
                    }
                    if (empty($child['model']) && !empty($device['model'])) {
                        $child['model'] = $device['model'];
                    }
                }
                $this->collectPartitions($device['children'], $disks);
            }
        }
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
