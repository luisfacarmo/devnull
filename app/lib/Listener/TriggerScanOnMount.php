<?php

declare(strict_types=1);

namespace OCA\DevNull\Listener;

use OCA\DevNull\Event\DiskMountedEvent;
use OCA\DevNull\Ingest\IngestPipeline;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Triggers the ingest pipeline when a disk is mounted.
 * Runs scan → dedup → classify automatically.
 */
class TriggerScanOnMount implements IEventListener
{
    public function __construct(
        private IngestPipeline $pipeline,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof DiskMountedEvent)) {
            return;
        }

        $this->logger->info('DevNull: Auto-ingest disparado após mount', [
            'device' => $event->device,
            'mountpoint' => $event->mountpoint,
            'user' => $event->userId,
        ]);

        // Run full pipeline: scan → dedup → classify
        $steps = ['scan', 'dedup', 'classify'];
        $result = $this->pipeline->execute($event->mountpoint, $steps, $event->userId);

        $this->logger->info('DevNull: Auto-ingest concluído', [
            'success' => $result['success'],
            'device' => $event->device,
        ]);
    }
}
