<?php

declare(strict_types=1);

namespace OCA\DevNull\Listener;

use OCA\DevNull\Event\IngestCompletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Sends a Nextcloud notification when an ingest pipeline completes.
 * Registered for IngestCompletedEvent in Application.php.
 */
class NotifyOnIngestComplete implements IEventListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof IngestCompletedEvent)) {
            return;
        }

        $status = $event->success ? 'concluído' : 'falhou';
        $this->logger->info('DevNull: ingest {status} para {mountpoint}', [
            'status' => $status,
            'mountpoint' => $event->mountpoint,
            'user' => $event->userId,
        ]);

        // TODO Sprint 4: Send actual Nextcloud notification via INotificationManager
    }
}
