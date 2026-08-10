<?php

declare(strict_types=1);

namespace OCA\DevNull\Db\Mapper;

use OCA\DevNull\Db\Entity\Mount;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Mount>
 */
class MountMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'devnull_mounts', Mount::class);
    }

    public function findByDiskId(int $diskId): ?Mount
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('disk_id', $qb->createNamedParameter($diskId)));

        try {
            return $this->findEntity($qb);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return null;
        }
    }

    /**
     * @return array<Mount>
     */
    public function findActiveMounts(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());

        return $this->findEntities($qb);
    }
}
