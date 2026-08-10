<?php

declare(strict_types=1);

namespace OCA\DevNull\Db\Mapper;

use OCA\DevNull\Db\Entity\Disk;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Disk>
 */
class DiskMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'devnull_disks', Disk::class);
    }

    public function findBySerial(string $serial): ?Disk
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('serial', $qb->createNamedParameter($serial)));

        try {
            return $this->findEntity($qb);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return null;
        }
    }
}
