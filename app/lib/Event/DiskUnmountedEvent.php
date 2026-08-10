<?php

declare(strict_types=1);

namespace OCA\DevNull\Event;

use OCP\EventDispatcher\Event;

/**
 * Fired when a disk is successfully unmounted.
 * Listeners may: remove storage registration, clean up, log.
 */
class DiskUnmountedEvent extends Event
{
    public function __construct(
        public readonly string $device,
        public readonly string $userId,
    ) {
        parent::__construct();
    }
}
