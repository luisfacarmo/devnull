<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Capability: Storage Registration
 *
 * Responsible for registering/unregistering mountpoints as Nextcloud
 * external storage entries. Handles visibility configuration per user.
 *
 * Domain boundary: ONLY manages NC external storage records. Never
 * mounts, detects, or scans.
 */
interface StorageRegistrarInterface
{
    /**
     * Register a mountpoint as Nextcloud external storage.
     *
     * @param string $mountpoint Filesystem path
     * @param string $label Display name in Nextcloud
     * @param string $ownerId User who initiated the mount
     * @param array<string> $visibleTo User IDs who can see this storage
     * @return int The Nextcloud storage ID
     */
    public function register(
        string $mountpoint,
        string $label,
        string $ownerId,
        array $visibleTo = []
    ): int;

    /**
     * Unregister an external storage entry.
     *
     * @param int $storageId Nextcloud storage ID
     */
    public function unregister(int $storageId): void;

    /**
     * Update visibility of an existing storage.
     *
     * @param int $storageId Nextcloud storage ID
     * @param array<string> $visibleTo User IDs
     */
    public function setVisibility(int $storageId, array $visibleTo): void;
}
