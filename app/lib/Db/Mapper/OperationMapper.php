<?php

declare(strict_types=1);

namespace OCA\DevNull\Db\Mapper;

use OCA\DevNull\Db\Entity\Operation;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Operation>
 */
class OperationMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'devnull_operations', Operation::class);
    }

    /**
     * @return array<Operation>
     */
    public function findByUser(string $userId, int $limit = 50): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('started_at', 'DESC')
            ->setMaxResults($limit);

        return $this->findEntities($qb);
    }

    /**
     * @return array<Operation>
     */
    public function findRunning(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter('running')));

        return $this->findEntities($qb);
    }
}
