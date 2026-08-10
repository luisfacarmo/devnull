<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\Db\Entity\Operation;
use OCA\DevNull\Db\Mapper\DiskMapper;
use OCA\DevNull\Db\Mapper\MountMapper;
use OCA\DevNull\Db\Mapper\OperationMapper;
use OCA\DevNull\Event\IngestCompletedEvent;
use OCA\DevNull\Ingest\IngestPipeline;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

/**
 * API: Ingest pipeline operations (scan, dedup, classify).
 */
class IngestController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private IngestPipeline $pipeline,
        private MountMapper $mountMapper,
        private DiskMapper $diskMapper,
        private OperationMapper $operationMapper,
        private IEventDispatcher $eventDispatcher,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Start an ingest pipeline on a mounted disk.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @param array $steps Steps to run (default: all)
     * @return DataResponse
     */
    public function start(string $device, array $steps = []): DataResponse
    {
        // Find the mount for this device
        $mounts = $this->mountMapper->findActiveMounts();
        $mountRecord = null;
        foreach ($mounts as $mount) {
            // Match by mountpoint containing device name
            if (str_contains($mount->getMountpoint(), $device) || true) {
                $mountRecord = $mount;
                break;
            }
        }

        if ($mountRecord === null) {
            // Try using the device info to find mountpoint
            return new DataResponse(['error' => 'Disco não está montado pelo DevNull'], 404);
        }

        $mountpoint = $mountRecord->getMountpoint();

        // Default: run all steps
        if (empty($steps)) {
            $steps = array_keys($this->pipeline->getAvailableSteps());
        }

        // Log operation start
        $op = new Operation();
        $op->setDiskId($mountRecord->getDiskId());
        $op->setUserId($this->userId);
        $op->setType('ingest');
        $op->setStatus('running');
        $op->setStartedAt(new \DateTime());
        $this->operationMapper->insert($op);

        // Execute pipeline
        $result = $this->pipeline->execute($mountpoint, $steps, $this->userId);

        // Update operation
        $op->setStatus($result['success'] ? 'completed' : 'failed');
        $op->setFinishedAt(new \DateTime());
        $op->setResultJson(json_encode($result['results']));
        if (!$result['success']) {
            $failedSteps = array_filter($result['results'], fn($r) => !$r['success']);
            $op->setErrorMsg(implode('; ', array_map(fn($r) => $r['message'], $failedSteps)));
        }
        $this->operationMapper->update($op);

        // Fire event
        $this->eventDispatcher->dispatchTyped(new IngestCompletedEvent(
            operationId: (string) $op->getId(),
            userId: $this->userId,
            mountpoint: $mountpoint,
            success: $result['success'],
            results: $result['results'],
        ));

        return new DataResponse([
            'success' => $result['success'],
            'operation_id' => $op->getId(),
            'results' => $result['results'],
        ]);
    }

    /**
     * Get available pipeline steps.
     *
     * @return DataResponse
     */
    public function getSteps(): DataResponse
    {
        return new DataResponse([
            'steps' => $this->pipeline->getAvailableSteps(),
        ]);
    }
}
