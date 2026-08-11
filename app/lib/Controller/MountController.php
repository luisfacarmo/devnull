<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * API: Mount and unmount operations.
 *
 * All dependencies resolved lazily via \OCP\Server::get() to prevent
 * DI failures from causing 404 on route resolution.
 */
class MountController extends OCSController
{
    private const MOUNT_BASE = '/media/devnull';
    private const DEVICE_PATTERN = '/^[a-z0-9]+$/';

    public function __construct(
        IRequest $request,
        private LoggerInterface $logger,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Mount a device and register as Nextcloud external storage.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @return DataResponse
     */
    public function mount(string $device): DataResponse
    {
        if (!$this->validateDevice($device)) {
            return new DataResponse(['error' => 'Nome de dispositivo inválido'], 400);
        }

        try {
            // Get mount strategy (lazy)
            $strategy = $this->getMountStrategy();

            // Get disk info
            $diskInfo = $this->findDisk($device);
            $label = $diskInfo?->label ?? $device;
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $label);
            $mountpoint = self::MOUNT_BASE . '/' . $safeName;

            // Mount
            $result = $strategy->mount($device, $mountpoint);
            if (!$result->success) {
                return new DataResponse(['error' => $result->error], 500);
            }

            $actualMountpoint = $result->mountpoint ?? $mountpoint;

            // Create .devnull marker
            $this->createMarker($actualMountpoint, $device, $label);

            // Register external storage via occ
            $storageRegistrar = \OCP\Server::get(\OCA\DevNull\Capability\StorageRegistrarInterface::class);
            $storageId = $storageRegistrar->register($actualMountpoint, $label, $this->userId, [$this->userId]);

            $this->logger->info('DevNull: disco montado', [
                'device' => $device,
                'mountpoint' => $actualMountpoint,
                'storage_id' => $storageId,
            ]);

            return new DataResponse([
                'success' => true,
                'mountpoint' => $actualMountpoint,
                'storage_id' => $storageId,
                'label' => $label,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: mount falhou', ['error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unmount a device.
     *
     * @param string $device Device name
     * @return DataResponse
     */
    public function unmount(string $device): DataResponse
    {
        if (!$this->validateDevice($device)) {
            return new DataResponse(['error' => 'Nome de dispositivo inválido'], 400);
        }

        try {
            // Find and remove the external storage for this device before unmounting
            $this->removeExternalStorage($device);

            // Unmount
            $strategy = $this->getMountStrategy();
            $result = $strategy->unmount($device);

            if (!$result->success) {
                return new DataResponse(['error' => $result->error], 500);
            }

            $this->logger->info('DevNull: disco ejetado + storage removido', ['device' => $device]);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: unmount falhou', ['error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function validateDevice(string $device): bool
    {
        return (bool) preg_match(self::DEVICE_PATTERN, $device);
    }

    private function findDisk(string $device): ?\OCA\DevNull\Capability\DiskInfo
    {
        try {
            $detector = \OCP\Server::get(\OCA\DevNull\Capability\DiskDetectorInterface::class);
            $disks = $detector->listAvailable();
            foreach ($disks as $disk) {
                if ($disk->name === $device) {
                    return $disk;
                }
            }
        } catch (\Exception) {
            // Detector unavailable
        }
        return null;
    }

    private function getMountStrategy(): \OCA\DevNull\Capability\MountStrategyInterface
    {
        try {
            $factory = \OCP\Server::get(\OCA\DevNull\Mount\MountStrategyFactory::class);
            return $factory->create();
        } catch (\Exception) {
            return new \OCA\DevNull\Mount\NullMountStrategy();
        }
    }

    private function removeExternalStorage(string $device): void
    {
        try {
            // Find the mountpoint for this device
            $diskInfo = $this->findDisk($device);
            $mountpoint = $diskInfo?->mountpoint;

            if ($mountpoint === null) {
                return;
            }

            // List external storages and find the one matching this mountpoint
            $phpBin = PHP_BINARY ?: '/usr/bin/php';
            $occPath = $this->findOccPath();
            if ($occPath === null) {
                return;
            }

            $cmd = sprintf('%s %s files_external:list --output=json 2>/dev/null', escapeshellarg($phpBin), escapeshellarg($occPath));
            $output = shell_exec($cmd);

            if (!$output) {
                return;
            }

            $storages = json_decode($output, true);
            if (!is_array($storages)) {
                return;
            }

            foreach ($storages as $storage) {
                $datadir = $storage['configuration']['datadir'] ?? '';
                // Match: datadir contains the mountpoint path
                if (str_contains($datadir, basename($mountpoint))) {
                    $storageId = $storage['mount_id'] ?? null;
                    if ($storageId) {
                        $deleteCmd = sprintf('%s %s files_external:delete --yes %s 2>/dev/null', escapeshellarg($phpBin), escapeshellarg($occPath), escapeshellarg((string) $storageId));
                        shell_exec($deleteCmd);
                        $this->logger->info('DevNull: external storage removido', ['id' => $storageId]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: falha ao remover external storage', ['error' => $e->getMessage()]);
        }
    }

    private function findOccPath(): ?string
    {
        $candidates = [
            '/var/www/nextcloud/occ',
            '/var/www/html/nextcloud/occ',
            dirname(__DIR__, 4) . '/occ',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    private function createMarker(string $mountpoint, string $device, string $label): void
    {
        $markerPath = rtrim($mountpoint, '/') . '/.devnull';
        $data = json_encode([
            'managed_by' => 'devnull',
            'version' => '1.0',
            'mounted_at' => date('c'),
            'mounted_by' => $this->userId,
            'device' => $device,
            'label' => $label,
        ], JSON_PRETTY_PRINT);
        @file_put_contents($markerPath, $data);
    }
}
