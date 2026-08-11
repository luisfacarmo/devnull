<?php

declare(strict_types=1);

namespace OCA\DevNull\Storage;

use OCA\DevNull\Capability\StorageRegistrarInterface;
use Psr\Log\LoggerInterface;

/**
 * Registers/unregisters mountpoints as Nextcloud external storage.
 * Uses shell_exec directly with the full occ path (bypasses SecureCommandRunner
 * because occ needs to run as www-data which we already are in web context).
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
        $this->logger->info('DevNull: registering external storage', [
            'mountpoint' => $mountpoint,
            'label' => $label,
            'owner' => $ownerId,
        ]);

        // Find occ path
        $occPath = $this->findOccPath();
        if ($occPath === null) {
            $this->logger->error('DevNull: occ not found');
            return 0;
        }

        // Find PHP binary
        $phpBin = PHP_BINARY ?: '/usr/bin/php';

        // Create external storage
        $cmd = sprintf(
            '%s %s files_external:create %s local null::null --config datadir=%s 2>&1',
            escapeshellarg($phpBin),
            escapeshellarg($occPath),
            escapeshellarg($label),
            escapeshellarg($mountpoint . '/')
        );

        $output = shell_exec($cmd);
        $this->logger->debug('DevNull: occ files_external:create output', ['output' => $output]);

        // Parse storage ID from output: "Storage created with id X"
        if ($output && preg_match('/id\s+(\d+)/', $output, $matches)) {
            $storageId = (int) $matches[1];

            // Scan the new storage so files appear
            $this->scanFiles($phpBin, $occPath, $ownerId);

            $this->logger->info('DevNull: storage registered', ['id' => $storageId]);
            return $storageId;
        }

        $this->logger->warning('DevNull: storage creation may have failed', ['output' => $output]);
        return 0;
    }

    public function unregister(int $storageId): void
    {
        if ($storageId <= 0) {
            return;
        }

        $occPath = $this->findOccPath();
        if ($occPath === null) {
            return;
        }

        $phpBin = PHP_BINARY ?: '/usr/bin/php';
        $cmd = sprintf(
            '%s %s files_external:delete --yes %s 2>&1',
            escapeshellarg($phpBin),
            escapeshellarg($occPath),
            escapeshellarg((string) $storageId)
        );

        $output = shell_exec($cmd);
        $this->logger->info('DevNull: storage unregistered', ['id' => $storageId, 'output' => $output]);
    }

    public function setVisibility(int $storageId, array $visibleTo): void
    {
        // External storages created without --applicable-users are global (visible to all)
        // For per-user visibility, we'd need files_external:applicable
        // For now, global access is fine for the MVP
    }

    private function scanFiles(string $phpBin, string $occPath, string $userId): void
    {
        // Scan user files so the new external storage content appears
        $cmd = sprintf(
            '%s %s files:scan %s 2>&1',
            escapeshellarg($phpBin),
            escapeshellarg($occPath),
            escapeshellarg($userId)
        );

        $output = shell_exec($cmd);
        $this->logger->debug('DevNull: files:scan output', ['output' => $output, 'user' => $userId]);
    }

    private function findOccPath(): ?string
    {
        // Try common locations
        $candidates = [
            '/var/www/nextcloud/occ',
            '/var/www/html/nextcloud/occ',
            dirname(__DIR__, 4) . '/occ', // Relative to app: app/lib/Storage/../../.. = nextcloud root
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try to find via OC::$SERVERROOT
        if (defined('OC_SERVERROOT')) {
            $path = \OC_SERVERROOT . '/occ';
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
