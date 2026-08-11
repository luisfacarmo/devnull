<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * API: Daemon webhook receiver.
 *
 * Receives hotplug notifications from the Python daemon.
 * Auth: shared token (X-DevNull-Token header) — no NC user session needed.
 *
 * @NoCSRFRequired
 * @NoAdminRequired
 * @PublicPage
 */
class DaemonController extends OCSController
{
    public function __construct(
        IRequest $request,
        private LoggerInterface $logger,
        private IConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Receive a hotplug event from the daemon.
     *
     * @NoCSRFRequired
     * @NoAdminRequired
     * @PublicPage
     *
     * @param string $event Event type: "disk_added" or "disk_removed"
     * @param array $disk Disk info payload
     * @return DataResponse
     */
    public function event(string $event, array $disk = []): DataResponse
    {
        // Verify daemon token
        if (!$this->verifyToken()) {
            return new DataResponse([
                'success' => false,
                'error' => 'Invalid token',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $this->logger->info('DevNull: daemon event received', [
            'event' => $event,
            'disk' => $disk,
        ]);

        switch ($event) {
            case 'disk_added':
                return $this->handleDiskAdded($disk);
            case 'disk_removed':
                return $this->handleDiskRemoved($disk);
            default:
                return new DataResponse([
                    'success' => false,
                    'error' => 'Unknown event type',
                    'code' => 'UNKNOWN_EVENT',
                ], 400);
        }
    }

    /**
     * Get daemon configuration (for the daemon to self-configure).
     *
     * @NoCSRFRequired
     * @NoAdminRequired
     * @PublicPage
     *
     * @return DataResponse
     */
    public function getConfig(): DataResponse
    {
        if (!$this->verifyToken()) {
            return new DataResponse(['success' => false, 'error' => 'Invalid token'], 401);
        }

        return new DataResponse([
            'success' => true,
            'config' => [
                'auto_mount' => $this->config->getAppValue('devnull', 'auto_mount_on_plug', 'false') === 'true',
                'mount_strategy' => $this->config->getAppValue('devnull', 'mount_strategy', 'udisks'),
                'default_user' => $this->config->getAppValue('devnull', 'default_mount_user', 'admin'),
            ],
        ]);
    }

    private function handleDiskAdded(array $disk): DataResponse
    {
        $deviceName = $disk['name'] ?? null;
        if ($deviceName === null) {
            return new DataResponse([
                'success' => false,
                'error' => 'Missing disk name',
                'code' => 'INVALID_PAYLOAD',
            ], 400);
        }

        $this->logger->info('DevNull: hotplug — disk added', [
            'device' => $deviceName,
            'label' => $disk['label'] ?? null,
            'fstype' => $disk['fstype'] ?? null,
        ]);

        // If auto-mount is enabled and the disk was auto-mounted by daemon,
        // trigger NC storage registration
        $mountpoint = $disk['mountpoint'] ?? null;
        if ($mountpoint !== null) {
            $this->registerMountedDisk($deviceName, $mountpoint, $disk);
        }

        return new DataResponse([
            'success' => true,
            'action' => $mountpoint ? 'registered' : 'acknowledged',
        ]);
    }

    private function handleDiskRemoved(array $disk): DataResponse
    {
        $deviceName = $disk['name'] ?? null;
        if ($deviceName === null) {
            return new DataResponse([
                'success' => false,
                'error' => 'Missing disk name',
                'code' => 'INVALID_PAYLOAD',
            ], 400);
        }

        $this->logger->info('DevNull: hotplug — disk removed', ['device' => $deviceName]);

        // Try to remove external storage for this device
        try {
            $storageId = $this->getStorageMapping($deviceName);
            if ($storageId > 0) {
                $storageRegistrar = \OCP\Server::get(\OCA\DevNull\Capability\StorageRegistrarInterface::class);
                $storageRegistrar->unregister($storageId);
                $this->clearStorageMapping($deviceName);
                $this->logger->info('DevNull: auto-removed storage on unplug', ['id' => $storageId]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: failed to clean storage on unplug', ['error' => $e->getMessage()]);
        }

        return new DataResponse(['success' => true, 'action' => 'cleaned']);
    }

    private function registerMountedDisk(string $device, string $mountpoint, array $disk): void
    {
        try {
            $defaultUser = $this->config->getAppValue('devnull', 'default_mount_user', 'admin');
            $label = $disk['label'] ?? $device;

            $storageRegistrar = \OCP\Server::get(\OCA\DevNull\Capability\StorageRegistrarInterface::class);
            $storageId = $storageRegistrar->register($mountpoint, $label, $defaultUser, [$defaultUser]);

            if ($storageId > 0) {
                $this->saveStorageMapping($device, $storageId);
                $this->logger->info('DevNull: auto-registered storage from daemon', [
                    'device' => $device,
                    'storage_id' => $storageId,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('DevNull: auto-register failed', ['error' => $e->getMessage()]);
        }
    }

    private function verifyToken(): bool
    {
        $token = $this->request->getHeader('X-DevNull-Token');
        $expected = $this->config->getAppValue('devnull', 'daemon_token', '');
        return $expected !== '' && hash_equals($expected, $token);
    }

    private function saveStorageMapping(string $device, int $storageId): void
    {
        $this->config->setAppValue('devnull', 'mount_storage_' . $device, (string) $storageId);
    }

    private function getStorageMapping(string $device): int
    {
        return (int) $this->config->getAppValue('devnull', 'mount_storage_' . $device, '0');
    }

    private function clearStorageMapping(string $device): void
    {
        $this->config->deleteAppValue('devnull', 'mount_storage_' . $device);
    }
}
