<?php

declare(strict_types=1);

namespace OCA\DevNull\Storage;

use OCA\DevNull\Capability\StorageRegistrarInterface;
use OCA\DevNull\Command\SecureCommandRunner;
use Psr\Log\LoggerInterface;

/**
 * Registers/unregisters mountpoints as Nextcloud external storage.
 * Uses occ files_external:create/delete commands via SecureCommandRunner.
 */
class NextcloudStorageRegistrar implements StorageRegistrarInterface
{
    private const OCC_PATH = '/var/www/nextcloud/occ';

    public function __construct(
        private SecureCommandRunner $commandRunner,
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

        // Create external storage via occ
        $args = [
            self::OCC_PATH,
            'files_external:create',
            $label,
            'local',
            'null::null',
            '--config', 'datadir=' . $mountpoint . '/',
        ];

        // Add applicable users
        if (!empty($visibleTo)) {
            foreach ($visibleTo as $userId) {
                $args[] = '--applicable-users';
                $args[] = $userId;
            }
        }

        try {
            $output = $this->commandRunner->run('php', $args);

            // Parse storage ID from output: "Storage created with id X"
            if (preg_match('/id\s+(\d+)/', $output, $matches)) {
                $storageId = (int) $matches[1];
                $this->logger->info('DevNull: storage registered', ['id' => $storageId]);
                return $storageId;
            }

            $this->logger->warning('DevNull: could not parse storage ID', ['output' => $output]);
            return 0;
        } catch (\RuntimeException $e) {
            $this->logger->error('DevNull: storage registration failed', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function unregister(int $storageId): void
    {
        if ($storageId <= 0) {
            return;
        }

        $this->logger->info('DevNull: unregistering storage', ['id' => $storageId]);

        try {
            $this->commandRunner->run('php', [
                self::OCC_PATH,
                'files_external:delete',
                '--yes',
                (string) $storageId,
            ]);
        } catch (\RuntimeException $e) {
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

        foreach ($visibleTo as $userId) {
            try {
                $this->commandRunner->run('php', [
                    self::OCC_PATH,
                    'files_external:applicable',
                    '--add-user', $userId,
                    (string) $storageId,
                ]);
            } catch (\RuntimeException $e) {
                $this->logger->error('DevNull: set visibility failed', [
                    'id' => $storageId,
                    'user' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
