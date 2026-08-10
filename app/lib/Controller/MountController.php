<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\Capability\MountStrategyInterface;
use OCA\DevNull\Capability\StorageRegistrarInterface;
use OCA\DevNull\Db\Mapper\DiskMapper;
use OCA\DevNull\Db\Mapper\MountMapper;
use OCA\DevNull\Event\DiskMountedEvent;
use OCA\DevNull\Event\DiskUnmountedEvent;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

/**
 * API: Mount and unmount operations.
 * Thin controller — orchestrates capabilities via events.
 */
class MountController extends OCSController
{
    private const MOUNT_BASE = '/media/devnull';
    private const DEVICE_PATTERN = '/^[a-z]+[0-9]*$/';

    public function __construct(
        string $appName,
        IRequest $request,
        private MountStrategyInterface $mountStrategy,
        private StorageRegistrarInterface $storageRegistrar,
        private IEventDispatcher $eventDispatcher,
        private MountMapper $mountMapper,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Mount a device.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @param array<string> $visibleTo User IDs who can see the storage
     * @return DataResponse
     */
    public function mount(string $device, array $visibleTo = []): DataResponse
    {
        if (!$this->validateDevice($device)) {
            return new DataResponse(['error' => 'Invalid device name'], 400);
        }

        $label = $device; // TODO: resolve label from disk info
        $mountpoint = self::MOUNT_BASE . '/' . $label;

        // 1. Mount via strategy
        $result = $this->mountStrategy->mount($device, $mountpoint);
        if (!$result->success) {
            return new DataResponse(['error' => $result->error], 500);
        }

        // 2. Register in Nextcloud
        $storageId = $this->storageRegistrar->register(
            $mountpoint,
            $label,
            $this->userId,
            $visibleTo
        );

        // 3. Fire event (listeners handle side effects: log, pipeline, etc.)
        $this->eventDispatcher->dispatchTyped(new DiskMountedEvent(
            device: $device,
            mountpoint: $mountpoint,
            userId: $this->userId,
            diskLabel: $label,
        ));

        return new DataResponse([
            'success' => true,
            'mountpoint' => $mountpoint,
            'storage_id' => $storageId,
        ]);
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
            return new DataResponse(['error' => 'Invalid device name'], 400);
        }

        // 1. Unmount via strategy
        $result = $this->mountStrategy->unmount($device);
        if (!$result->success) {
            return new DataResponse(['error' => $result->error], 500);
        }

        // 2. Fire event (listeners handle: unregister storage, clean up)
        $this->eventDispatcher->dispatchTyped(new DiskUnmountedEvent(
            device: $device,
            userId: $this->userId,
        ));

        return new DataResponse(['success' => true]);
    }

    private function validateDevice(string $device): bool
    {
        return (bool) preg_match(self::DEVICE_PATTERN, $device);
    }
}
