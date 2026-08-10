<?php

declare(strict_types=1);

namespace OCA\DevNull\Storage;

use OCA\DevNull\Capability\StorageRegistrarInterface;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Registers/unregisters mountpoints as Nextcloud external storage.
 * Uses GlobalStoragesService (files_external app) internally.
 *
 * NOTE: Full implementation requires files_external to be enabled.
 * This is the MVP implementation — Sprint 2 will add full GlobalStoragesService usage.
 */
class NextcloudStorageRegistrar implements StorageRegistrarInterface
{
    public function __construct(
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    public function register(
        string $mountpoint,
        string $label,
        string $ownerId,
        array $visibleTo = []
    ): int {
        // TODO Sprint 2: Use GlobalStoragesService to create real external storage
        // For now, log the intent and return a placeholder ID
        $this->logger->info('DevNull: registering storage', [
            'mountpoint' => $mountpoint,
            'label' => $label,
            'owner' => $ownerId,
            'visible_to' => $visibleTo,
        ]);

        // Placeholder: store as app config for now
        $storageId = (int) $this->config->getAppValue('devnull', 'next_storage_id', '1');
        $this->config->setAppValue('devnull', 'next_storage_id', (string) ($storageId + 1));

        return $storageId;
    }

    public function unregister(int $storageId): void
    {
        // TODO Sprint 2: Use GlobalStoragesService to remove external storage
        $this->logger->info('DevNull: unregistering storage', [
            'storage_id' => $storageId,
        ]);
    }

    public function setVisibility(int $storageId, array $visibleTo): void
    {
        // TODO Sprint 2: Use GlobalStoragesService to update applicable users
        $this->logger->info('DevNull: updating storage visibility', [
            'storage_id' => $storageId,
            'visible_to' => $visibleTo,
        ]);
    }
}
