<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;

/**
 * API: Status and progress reporting.
 * Uses raw DB to avoid DI failures when tables don't exist.
 */
class StatusController extends OCSController
{
    public function __construct(
        IRequest $request,
        private IDBConnection $db,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get current status for the requesting user.
     *
     * @NoAdminRequired
     */
    public function index(): DataResponse
    {
        $mounts = [];
        $operations = [];

        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')->from('devnull_mounts');
            $result = $qb->executeQuery();
            while ($row = $result->fetch()) {
                $mounts[] = [
                    'id' => (int) $row['id'],
                    'disk_id' => (int) $row['disk_id'],
                    'mountpoint' => $row['mountpoint'],
                    'mounted_at' => $row['mounted_at'],
                ];
            }
            $result->closeCursor();
        } catch (\Exception) {
            // Table doesn't exist yet
        }

        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from('devnull_operations')
                ->where($qb->expr()->eq('status', $qb->createNamedParameter('running')));
            $result = $qb->executeQuery();
            while ($row = $result->fetch()) {
                $operations[] = [
                    'id' => (int) $row['id'],
                    'type' => $row['type'],
                    'status' => $row['status'],
                    'started_at' => $row['started_at'],
                ];
            }
            $result->closeCursor();
        } catch (\Exception) {
            // Table doesn't exist yet
        }

        return new DataResponse([
            'success' => true,
            'transport' => 'polling',
            'status' => [
                'mounts' => $mounts,
                'operations' => $operations,
                'timestamp' => time(),
            ],
        ]);
    }
}
