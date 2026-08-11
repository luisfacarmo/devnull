<?php

declare(strict_types=1);

namespace OCA\DevNull\Storage;

use OCA\DevNull\Capability\StorageRegistrarInterface;
use Psr\Log\LoggerInterface;

/**
 * Registers/unregisters mountpoints as Nextcloud external storage.
 *
 * Uses the internal PHP API of files_external (GlobalStoragesService)
 * instead of calling occ as subprocess. This avoids:
 * - DB lock conflicts when called from web request
 * - Autoloader re-bootstrap overhead
 * - Permission/path issues with shell_exec
 *
 * Requires: files_external app to be enabled in Nextcloud.
 */
class NextcloudStorageRegistrar implements StorageRegistrarInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function register(
        string $mountpoint,
        string $label,
        string $ownerId,
        array $visibleTo = []
    ): int {
        $this->logger->info('DevNull: registering external storage via PHP API', [
            'mountpoint' => $mountpoint,
            'label' => $label,
            'owner' => $ownerId,
        ]);

        try {
            // Get files_external services
            $backendService = \OCP\Server::get(\OCA\Files_External\Service\BackendService::class);
            $globalService = \OCP\Server::get(\OCA\Files_External\Service\GlobalStoragesService::class);

            // Get "local" storage backend and "null::null" auth mechanism
            $storageBackend = $backendService->getBackend('local');
            $authBackend = $backendService->getAuthMechanism('null::null');

            if ($storageBackend === null || $authBackend === null) {
                $this->logger->error('DevNull: files_external backends not available (is the app enabled?)');
                return 0;
            }

            // Create StorageConfig
            $mount = new \OCA\Files_External\Lib\StorageConfig();
            $mount->setMountPoint($label);
            $mount->setBackend($storageBackend);
            $mount->setAuthMechanism($authBackend);
            $mount->setBackendOptions([
                'datadir' => rtrim($mountpoint, '/') . '/',
            ]);

            // Set applicable users (or all if empty)
            if (!empty($visibleTo)) {
                $mount->setApplicableUsers($visibleTo);
            }

            // Save
            $globalService->addStorage($mount);
            $storageId = $mount->getId();

            $this->logger->info('DevNull: storage registered', ['id' => $storageId]);

            // Trigger file scan for the owner so content appears
            $this->scanUserFiles($ownerId, $label);

            return $storageId;
        } catch (\Exception $e) {
            $this->logger->error('DevNull: storage registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 0;
        }
    }

    public function unregister(int $storageId): void
    {
        if ($storageId <= 0) {
            return;
        }

        try {
            $globalService = \OCP\Server::get(\OCA\Files_External\Service\GlobalStoragesService::class);
            $globalService->removeStorage($storageId);
            $this->logger->info('DevNull: storage unregistered', ['id' => $storageId]);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: storage unregister failed', [
                'id' => $storageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function setVisibility(int $storageId, array $visibleTo): void
    {
        if ($storageId <= 0) {
            return;
        }

        try {
            $globalService = \OCP\Server::get(\OCA\Files_External\Service\GlobalStoragesService::class);
            $storage = $globalService->getStorage($storageId);
            $storage->setApplicableUsers($visibleTo);
            $globalService->updateStorage($storage);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: set visibility failed', [
                'id' => $storageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger a real filesystem scan on the newly mounted storage.
     *
     * Uses \OC\Files\Utils\Scanner (same engine as `occ files:scan`)
     * but invoked directly via PHP — no subprocess needed.
     *
     * Scans only the specific mount label path to avoid full-user scan overhead.
     */
    private function scanUserFiles(string $userId, string $label = ''): void
    {
        try {
            // Setup user filesystem (required before scanning)
            \OC_Util::setupFS($userId);

            // Build scan path: /{userId}/files or /{userId}/files/{label}
            $scanPath = '/' . $userId . '/files';
            if ($label !== '') {
                $scanPath .= '/' . $label;
            }

            // Use the Scanner utility (same as occ files:scan internals)
            $scanner = new \OC\Files\Utils\Scanner(
                $userId,
                \OCP\Server::get(\OCP\Files\Storage\IStorageFactory::class),
                \OCP\Server::get(\OCP\IDBConnection::class),
                \OCP\Server::get(\OCP\EventDispatcher\IEventDispatcher::class),
                $this->logger,
            );

            $scanner->scan($scanPath, $recursive = true, null);

            $this->logger->info('DevNull: file scan completed', [
                'user' => $userId,
                'path' => $scanPath,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: file scan failed (content may need manual scan)', [
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
