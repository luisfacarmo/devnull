<?php

declare(strict_types=1);

namespace OCA\DevNull\Listener;

use OCA\DevNull\Event\DiskUnmountedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Logs unmount events for audit trail.
 * Registered for DiskUnmountedEvent in Application.php.
 */
class LogOnUnmount implements IEventListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof DiskUnmountedEvent)) {
            return;
        }

        $this->logger->info('DevNull: disco ejetado', [
            'device' => $event->device,
            'user' => $event->userId,
        ]);
    }
}
