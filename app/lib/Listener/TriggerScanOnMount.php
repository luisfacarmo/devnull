<?php

declare(strict_types=1);

namespace OCA\DevNull\Listener;

use OCA\DevNull\Event\DiskMountedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Optionally triggers a file scan when a disk is mounted.
 * Registered for DiskMountedEvent in Application.php.
 *
 * Currently logs only — full scan pipeline comes in Sprint 4.
 */
class TriggerScanOnMount implements IEventListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof DiskMountedEvent)) {
            return;
        }

        $this->logger->info('DevNull: disco montado, scan disponível', [
            'device' => $event->device,
            'mountpoint' => $event->mountpoint,
            'user' => $event->userId,
        ]);

        // TODO Sprint 4: Trigger IngestPipeline with configured steps
    }
}
