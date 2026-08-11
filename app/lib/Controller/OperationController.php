<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;

/**
 * API: Operation history (logs).
 *
 * Uses raw DB query to avoid DI issues with Mapper when table doesn't exist.
 */
class OperationController extends OCSController
{
    public function __construct(
        IRequest $request,
        private IDBConnection $db,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * List recent operations for the current user.
     *
     * @NoAdminRequired
     * @param int $limit Max results (default 20)
     * @return DataResponse
     */
    public function list(int $limit = 20): DataResponse
    {
        if ($this->userId === null) {
            return new DataResponse(['success' => true, 'operations' => []]);
        }

        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from('devnull_operations')
                ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
                ->orderBy('started_at', 'DESC')
                ->setMaxResults(min($limit, 100));

            $result = $qb->executeQuery();
            $operations = [];

            while ($row = $result->fetch()) {
                $operations[] = [
                    'id' => (int) $row['id'],
                    'disk_id' => (int) $row['disk_id'],
                    'type' => $row['type'],
                    'status' => $row['status'],
                    'started_at' => $row['started_at'],
                    'finished_at' => $row['finished_at'],
                    'error' => $row['error_msg'],
                ];
            }
            $result->closeCursor();

            return new DataResponse(['success' => true, 'operations' => $operations]);
        } catch (\Exception) {
            // Table doesn't exist yet — return empty gracefully
            return new DataResponse(['success' => true, 'operations' => []]);
        }
    }
}
