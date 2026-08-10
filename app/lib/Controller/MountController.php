<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\Capability\StorageRegistrarInterface;
use OCA\DevNull\Capability\DiskDetectorInterface;
use OCA\DevNull\Mount\MountStrategyFactory;
use OCA\DevNull\Mount\NullMountStrategy;
use OCA\DevNull\Db\Entity\Disk;
use OCA\DevNull\Db\Entity\Mount;
use OCA\DevNull\Db\Entity\Operation;
use OCA\DevNull\Db\Mapper\DiskMapper;
use OCA\DevNull\Db\Mapper\MountMapper;
use OCA\DevNull\Db\Mapper\OperationMapper;
use OCA\DevNull\Event\DiskMountedEvent;
use OCA\DevNull\Event\DiskUnmountedEvent;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

/**
 * API: Mount and unmount operations.
 * Full flow: validate → mount → .devnull marker → register storage → persist → fire event.
 */
class MountController extends OCSController
{
    private const MOUNT_BASE = '/media/devnull';
    private const DEVICE_PATTERN = '/^[a-z0-9]+$/';

    public function __construct(
        string $appName,
        IRequest $request,
        private StorageRegistrarInterface $storageRegistrar,
        private DiskDetectorInterface $detector,
        private IEventDispatcher $eventDispatcher,
        private DiskMapper $diskMapper,
        private MountMapper $mountMapper,
        private OperationMapper $operationMapper,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Mount a device and register as Nextcloud external storage.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @return DataResponse
     */
    public function mount(string $device): DataResponse
    {
        // 1. Validate device name
        if (!$this->validateDevice($device)) {
            return new DataResponse(['error' => 'Nome de dispositivo inválido'], 400);
        }

        // 2. Get disk info from detector
        $diskInfo = $this->findDisk($device);
        $label = $diskInfo?->label ?? $device;
        $mountpoint = self::MOUNT_BASE . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label);

        // 3. Mount via strategy (resolved lazily)
        $mountStrategy = $this->getMountStrategy();
        $result = $mountStrategy->mount($device, $mountpoint);
        if (!$result->success) {
            return new DataResponse(['error' => $result->error], 500);
        }

        // 4. Create .devnull marker file
        $this->createMarker($result->mountpoint ?? $mountpoint, $device, $label);

        // 5. Register as Nextcloud external storage
        $storageId = $this->storageRegistrar->register(
            $result->mountpoint ?? $mountpoint,
            $label,
            $this->userId,
            [$this->userId]
        );

        // 6. Persist disk + mount records
        $diskEntity = $this->persistDisk($device, $diskInfo);
        $this->persistMount($diskEntity, $storageId, $result->mountpoint ?? $mountpoint);
        $this->logOperation($diskEntity, 'mount', 'completed');

        // 7. Fire event
        $this->eventDispatcher->dispatchTyped(new DiskMountedEvent(
            device: $device,
            mountpoint: $result->mountpoint ?? $mountpoint,
            userId: $this->userId,
            diskLabel: $label,
        ));

        return new DataResponse([
            'success' => true,
            'mountpoint' => $result->mountpoint ?? $mountpoint,
            'storage_id' => $storageId,
            'label' => $label,
        ]);
    }

    /**
     * Unmount a device and remove external storage.
     *
     * @param string $device Device name
     * @return DataResponse
     */
    public function unmount(string $device): DataResponse
    {
        // 1. Validate
        if (!$this->validateDevice($device)) {
            return new DataResponse(['error' => 'Nome de dispositivo inválido'], 400);
        }

        // 2. Find active mount record
        $diskEntity = $this->diskMapper->findBySerial($this->getSerialForDevice($device));
        if ($diskEntity === null) {
            return new DataResponse(['error' => 'Disco não encontrado'], 404);
        }

        $mountRecord = $this->mountMapper->findByDiskId($diskEntity->getId());
        if ($mountRecord === null) {
            return new DataResponse(['error' => 'Disco não está montado pelo DevNull'], 404);
        }

        // 3. Unregister external storage
        if ($mountRecord->getStorageId()) {
            $this->storageRegistrar->unregister($mountRecord->getStorageId());
        }

        // 4. Remove .devnull marker
        $this->removeMarker($mountRecord->getMountpoint());

        // 5. Unmount via strategy
        $mountStrategy = $this->getMountStrategy();
        $result = $mountStrategy->unmount($device);
        if (!$result->success) {
            return new DataResponse(['error' => $result->error], 500);
        }

        // 6. Remove mount record
        $this->mountMapper->delete($mountRecord);
        $this->logOperation($diskEntity, 'unmount', 'completed');

        // 7. Fire event
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

    private function findDisk(string $device): ?\OCA\DevNull\Capability\DiskInfo
    {
        $disks = $this->detector->listAvailable();
        foreach ($disks as $disk) {
            if ($disk->name === $device) {
                return $disk;
            }
        }
        return null;
    }

    private function createMarker(string $mountpoint, string $device, string $label): void
    {
        $markerPath = rtrim($mountpoint, '/') . '/.devnull';
        $markerData = json_encode([
            'managed_by' => 'devnull',
            'version' => '1.0',
            'mounted_at' => date('c'),
            'mounted_by' => $this->userId,
            'device' => $device,
            'label' => $label,
        ], JSON_PRETTY_PRINT);

        @file_put_contents($markerPath, $markerData);
    }

    private function removeMarker(string $mountpoint): void
    {
        $markerPath = rtrim($mountpoint, '/') . '/.devnull';
        if (file_exists($markerPath)) {
            @unlink($markerPath);
        }
    }

    private function persistDisk(string $device, ?\OCA\DevNull\Capability\DiskInfo $info): Disk
    {
        $serial = $info?->serial ?? $device;
        $existing = $this->diskMapper->findBySerial($serial);

        if ($existing !== null) {
            $existing->setLastSeen(new \DateTime());
            return $this->diskMapper->update($existing);
        }

        $disk = new Disk();
        $disk->setSerial($serial);
        $disk->setLabel($info?->label);
        $disk->setModel($info?->model);
        $disk->setFstype($info?->fstype);
        $disk->setFirstSeen(new \DateTime());
        $disk->setLastSeen(new \DateTime());

        return $this->diskMapper->insert($disk);
    }

    private function persistMount(Disk $disk, int $storageId, string $mountpoint): void
    {
        $mount = new Mount();
        $mount->setDiskId($disk->getId());
        $mount->setUserId($this->userId);
        $mount->setStorageId($storageId);
        $mount->setMountpoint($mountpoint);
        $mount->setMountedAt(new \DateTime());

        $this->mountMapper->insert($mount);
    }

    private function logOperation(Disk $disk, string $type, string $status): void
    {
        $op = new Operation();
        $op->setDiskId($disk->getId());
        $op->setUserId($this->userId);
        $op->setType($type);
        $op->setStatus($status);
        $op->setStartedAt(new \DateTime());
        $op->setFinishedAt(new \DateTime());

        $this->operationMapper->insert($op);
    }

    private function getSerialForDevice(string $device): string
    {
        $info = $this->findDisk($device);
        return $info?->serial ?? $device;
    }

    private function getMountStrategy(): \OCA\DevNull\Capability\MountStrategyInterface
    {
        try {
            $factory = \OCP\Server::get(MountStrategyFactory::class);
            return $factory->create();
        } catch (\Exception) {
            return new NullMountStrategy();
        }
    }
}
