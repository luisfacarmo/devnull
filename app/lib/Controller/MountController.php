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
            return new DataResponse([
                'success' => false,
                'error' => 'Nome de dispositivo inválido',
                'code' => 'INVALID_DEVICE',
            ], 400);
        }

        if ($this->userId === null) {
            return new DataResponse([
                'success' => false,
                'error' => 'Usuário não autenticado',
                'code' => 'UNAUTHENTICATED',
            ], 401);
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
                return new DataResponse([
                    'success' => false,
                    'error' => $result->error ?? 'Mount falhou',
                    'code' => 'MOUNT_FAILED',
                ], 500);
            }

            $actualMountpoint = $result->mountpoint ?? $mountpoint;

            // Create .devnull marker
            $this->createMarker($actualMountpoint, $device, $label);

            // Register external storage (skip if already registered for this device)
            $existingStorageId = $this->getStorageMapping($device);
            if ($existingStorageId > 0) {
                $storageId = $existingStorageId;
                $this->logger->info('DevNull: storage already registered, skipping', ['id' => $storageId]);
            } else {
                $storageRegistrar = \OCP\Server::get(\OCA\DevNull\Capability\StorageRegistrarInterface::class);
                $storageId = $storageRegistrar->register($actualMountpoint, $label, $this->userId, [$this->userId]);
                $this->saveStorageMapping($device, $storageId);
            }

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
            return new DataResponse([
                'success' => false,
                'error' => 'Mount falhou: ' . $e->getMessage(),
                'code' => 'MOUNT_ERROR',
            ], 500);
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
            return new DataResponse([
                'success' => false,
                'error' => 'Nome de dispositivo inválido',
                'code' => 'INVALID_DEVICE',
            ], 400);
        }

        try {
            // Remove external storage BEFORE unmounting (while we still have info)
            $this->removeExternalStorageForDevice($device);

            $strategy = $this->getMountStrategy();
            $result = $strategy->unmount($device);

            if (!$result->success) {
                return new DataResponse([
                    'success' => false,
                    'error' => $result->error ?? 'Eject falhou',
                    'code' => 'UNMOUNT_FAILED',
                ], 500);
            }

            $this->logger->info('DevNull: disco ejetado', ['device' => $device]);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: unmount falhou', ['error' => $e->getMessage()]);
            return new DataResponse([
                'success' => false,
                'error' => 'Eject falhou: ' . $e->getMessage(),
                'code' => 'UNMOUNT_ERROR',
            ], 500);
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

    private function removeExternalStorageForDevice(string $device): void
    {
        try {
            $storageId = $this->getStorageMapping($device);
            if ($storageId <= 0) {
                $this->logger->warning('DevNull: no stored storageId for device, trying fallback', ['device' => $device]);
                $this->removeExternalStorageByDetection($device);
                return;
            }

            // Use StorageRegistrar to unregister directly by ID
            $storageRegistrar = \OCP\Server::get(\OCA\DevNull\Capability\StorageRegistrarInterface::class);
            $storageRegistrar->unregister($storageId);

            // Clear the mapping
            $this->clearStorageMapping($device);
            $this->logger->info('DevNull: storage removido no eject via storageId', ['id' => $storageId]);
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: falha ao remover storage no eject', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fallback: try to find and remove storage by matching datadir.
     * Only used when no saved storageId mapping exists.
     */
    private function removeExternalStorageByDetection(string $device): void
    {
        try {
            $diskInfo = $this->findDisk($device);
            $mountpoint = $diskInfo?->mountpoint;
            if ($mountpoint === null) {
                return;
            }

            $globalService = \OCP\Server::get(\OCA\Files_External\Service\GlobalStoragesService::class);
            $allStorages = $globalService->getStorages();

            $mountBasename = basename($mountpoint);
            foreach ($allStorages as $storage) {
                $datadir = $storage->getBackendOptions()['datadir'] ?? '';
                if (str_contains($datadir, $mountBasename)) {
                    $globalService->removeStorage($storage->getId());
                    $this->logger->info('DevNull: storage removido no eject (fallback)', ['id' => $storage->getId()]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: fallback storage removal failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Save device → storageId mapping using Nextcloud's IConfig.
     * Uses app config keys: "mount_storage_{device}" = storageId
     */
    private function saveStorageMapping(string $device, int $storageId): void
    {
        if ($storageId <= 0) {
            return;
        }
        try {
            $config = \OCP\Server::get(\OCP\IConfig::class);
            $config->setAppValue('devnull', 'mount_storage_' . $device, (string) $storageId);
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: failed to save storage mapping', ['error' => $e->getMessage()]);
        }
    }

    private function getStorageMapping(string $device): int
    {
        try {
            $config = \OCP\Server::get(\OCP\IConfig::class);
            $value = $config->getAppValue('devnull', 'mount_storage_' . $device, '0');
            return (int) $value;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function clearStorageMapping(string $device): void
    {
        try {
            $config = \OCP\Server::get(\OCP\IConfig::class);
            $config->deleteAppValue('devnull', 'mount_storage_' . $device);
        } catch (\Exception $e) {
            // Non-critical
        }
    }
}