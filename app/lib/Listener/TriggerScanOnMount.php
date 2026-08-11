<?php

declare(strict_types=1);

namespace OCA\DevNull\Listener;

use OCA\DevNull\Event\DiskMountedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Logs mount events. Full ingest pipeline will run via BackgroundJob (Sprint 5).
 * NEVER runs heavy operations synchronously in an event listener.
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

        $this->logger->info('DevNull: disco montado — pipeline disponível para execução manual', [
            'device' => $event->device,
            'mountpoint' => $event->mountpoint,
            'user' => $event->userId,
        ]);

        // TODO Sprint 5: Schedule IngestBackgroundJob instead of running synchronously
        // $jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);
        // $jobList->add(IngestBackgroundJob::class, ['mountpoint' => $event->mountpoint, ...]);
    }
}
