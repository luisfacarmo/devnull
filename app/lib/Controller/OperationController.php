<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\Db\Mapper\OperationMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * API: Operation history (logs).
 */
class OperationController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private OperationMapper $operationMapper,
        private ?string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * List recent operations for the current user.
     *
     * @param int $limit Max results (default 20)
     * @return DataResponse
     */
    public function list(int $limit = 20): DataResponse
    {
        try {
            $operations = $this->operationMapper->findByUser($this->userId, $limit);

            $result = array_map(fn($op) => [
                'id' => $op->getId(),
                'disk_id' => $op->getDiskId(),
                'type' => $op->getType(),
                'status' => $op->getStatus(),
                'started_at' => $op->getStartedAt(),
                'finished_at' => $op->getFinishedAt(),
                'error' => $op->getErrorMsg(),
            ], $operations);

            return new DataResponse(['operations' => $result]);
        } catch (\Exception $e) {
            // Table may not exist yet (migration not run)
            return new DataResponse(['operations' => [], 'warning' => 'Banco de dados ainda não inicializado']);
        }
    }
}
