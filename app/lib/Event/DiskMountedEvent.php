<?php

declare(strict_types=1);

namespace OCA\DevNull\Event;

use OCP\EventDispatcher\Event;

/**
 * Fired when a disk is successfully mounted.
 * Listeners may: trigger pipeline, send notification, log.
 */
class DiskMountedEvent extends Event
{
    public function __construct(
        public readonly string $device,
        public readonly string $mountpoint,
        public readonly string $userId,
        public readonly ?string $diskLabel,
    ) {
        parent::__construct();
    }
}
