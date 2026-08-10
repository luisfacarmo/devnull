<?php

declare(strict_types=1);

namespace OCA\DevNull\Event;

use OCP\EventDispatcher\Event;

/**
 * Fired when an ingest pipeline completes (success or failure).
 * Listeners may: notify user, generate report, update dashboard.
 */
class IngestCompletedEvent extends Event
{
    public function __construct(
        public readonly string $operationId,
        public readonly string $userId,
        public readonly string $mountpoint,
        public readonly bool $success,
        public readonly array $results,
    ) {
        parent::__construct();
    }
}
