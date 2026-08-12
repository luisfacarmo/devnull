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
        // Ensure all strings are valid UTF-8 regardless of filesystem locale.
        // Filesystems may return paths in the OS locale (e.g. latin1 on some
        // systems). We normalise to UTF-8 here so the Nextcloud DB and Scanner
        // never see mojibake or replacement characters ("B?RBARA" style).
        $mountpoint = $this->toUtf8($mountpoint);
        $label      = $this->toUtf8($label);

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

            // datadir must end with '/' and be valid UTF-8
            $datadir = rtrim($mountpoint, '/') . '/';

            // Check if a storage for this exact datadir already exists.
            // This prevents creating duplicate storages on repeated mount/unmount
            // cycles — the filecache from the previous mount is reused as-is.
            $existingId = $this->findStorageByDatadir($datadir, $globalService);
            if ($existingId > 0) {
                $this->logger->info('DevNull: reusing existing storage for datadir', [
                    'id'      => $existingId,
                    'datadir' => $datadir,
                ]);
                // Schedule a selective scan in case new files were added since last mount
                $this->scheduleScan($ownerId, $mountpoint, $label);
                return $existingId;
            }

            // Create StorageConfig
            $mount = new \OCA\Files_External\Lib\StorageConfig();
            $mount->setMountPoint($label);
            $mount->setBackend($storageBackend);
            $mount->setAuthMechanism($authBackend);
            $mount->setBackendOptions([
                'datadir' => $datadir,
            ]);

            // Set applicable users (or all if empty)
            if (!empty($visibleTo)) {
                $mount->setApplicableUsers($visibleTo);
            }

            // Save
            $globalService->addStorage($mount);
            $storageId = $mount->getId();

            $this->logger->info('DevNull: storage registered', ['id' => $storageId]);

            // Schedule a background scan instead of running synchronously.
            // The cron will pick this up within seconds (web cron) or the next
            // scheduled run. This avoids the timing issue where the VFS hasn't
            // mounted the new storage yet within the same HTTP request.
            $this->scheduleScan($ownerId, $mountpoint, $label);

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
                'id'    => $storageId,
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
     * Schedule a background scan job for the newly mounted storage.
     *
     * Using QueuedJob ensures the scan runs after the current HTTP request
     * completes, by which time the Nextcloud VFS has fully registered the
     * new external storage and the Scanner can find it.
     */
    private function scheduleScan(string $userId, string $mountpoint, string $label): void
    {
        try {
            $jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);
            $jobList->add(\OCA\DevNull\BackgroundJob\ScanMountedStorage::class, [
                'userId'     => $userId,
                'mountpoint' => $mountpoint,
                'label'      => $label,
            ]);
            $this->logger->info('DevNull: scan job scheduled', [
                'user'  => $userId,
                'label' => $label,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: failed to schedule scan job', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find an existing external storage registration by its datadir path.
     *
     * Returns the storage ID if found, 0 otherwise.
     * Comparison is done after UTF-8 normalisation of both sides.
     */
    private function findStorageByDatadir(string $datadir, object $globalService): int
    {
        try {
            $allStorages = $globalService->getStorages();
            $normalised = $this->toUtf8(rtrim($datadir, '/') . '/');
            foreach ($allStorages as $storage) {
                $existing = $this->toUtf8(
                    rtrim($storage->getBackendOptions()['datadir'] ?? '', '/') . '/'
                );
                if ($existing === $normalised) {
                    return $storage->getId();
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('DevNull: findStorageByDatadir failed', ['error' => $e->getMessage()]);
        }
        return 0;
    }

    /**
     * Normalise a string to valid UTF-8.
     *
     * Handles three common cases:
     *  1. String is already valid UTF-8 → returned as-is.
     *  2. String is in the system locale (e.g. ISO-8859-1) → converted via iconv.
     *  3. iconv unavailable or fails → mb_convert_encoding fallback with
     *     //IGNORE so invalid bytes are dropped rather than becoming '?'.
     */
    private function toUtf8(string $s): string
    {
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }

        // Try iconv with the system locale first (most accurate)
        if (function_exists('iconv')) {
            $locale = setlocale(LC_CTYPE, '0') ?: 'UTF-8';
            // Extract charset from locale string like "pt_BR.UTF-8" or "en_US.iso88591"
            $charset = 'UTF-8';
            if (preg_match('/\.(.+)$/', $locale, $m)) {
                $charset = $m[1];
            }
            if (strcasecmp($charset, 'UTF-8') !== 0 && strcasecmp($charset, 'UTF8') !== 0) {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $s);
                if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                    return $converted;
                }
            }
            // Generic fallback: try ISO-8859-1 (covers most Western labels)
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        // Last resort: mb drops invalid bytes rather than replacing with '?'
        return mb_convert_encoding($s, 'UTF-8', 'auto');
    }
}
