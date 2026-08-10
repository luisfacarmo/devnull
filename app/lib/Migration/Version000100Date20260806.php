<?php

declare(strict_types=1);

namespace OCA\DevNull\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema: disks, operations, mounts.
 */
class Version000100Date20260806 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // oc_devnull_disks — known disks (identified by serial)
        if (!$schema->hasTable('devnull_disks')) {
            $table = $schema->createTable('devnull_disks');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('serial', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('label', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('model', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('fstype', Types::STRING, [
                'notnull' => false,
                'length' => 50,
            ]);
            $table->addColumn('size_bytes', Types::BIGINT, [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('first_seen', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('last_seen', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['serial'], 'devnull_disk_serial_idx');
        }

        // oc_devnull_operations — operation log
        if (!$schema->hasTable('devnull_operations')) {
            $table = $schema->createTable('devnull_operations');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('disk_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('type', Types::STRING, [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('started_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('finished_at', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('result_json', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('error_msg', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['disk_id'], 'devnull_op_disk_idx');
            $table->addIndex(['user_id'], 'devnull_op_user_idx');
            $table->addIndex(['status'], 'devnull_op_status_idx');
        }

        // oc_devnull_mounts — active mount records
        if (!$schema->hasTable('devnull_mounts')) {
            $table = $schema->createTable('devnull_mounts');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('disk_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('storage_id', Types::BIGINT, [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('mountpoint', Types::STRING, [
                'notnull' => true,
                'length' => 512,
            ]);
            $table->addColumn('mounted_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['disk_id'], 'devnull_mount_disk_idx');
        }

        return $schema;
    }
}
