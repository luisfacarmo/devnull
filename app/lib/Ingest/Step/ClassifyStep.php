<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest\Step;

use OCA\DevNull\Ingest\IngestStepInterface;
use Psr\Log\LoggerInterface;

/**
 * Classify step: schedules Recognize AI classification as a BackgroundJob.
 *
 * Cannot call `occ recognize:classify` from HTTP context.
 * Instead, adds a one-time job to Nextcloud's job queue.
 * Recognize will process new files asynchronously via cron.
 */
class ClassifyStep implements IngestStepInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function getId(): string
    {
        return 'classify';
    }

    public function getDescription(): string
    {
        return 'Classificar mídia com IA (agendado em background)';
    }

    public function execute(string $mountpoint, string $userId): array
    {
        $this->logger->info('DevNull: ClassifyStep iniciado', [
            'mountpoint' => $mountpoint,
            'user' => $userId,
        ]);

        try {
            // Check if recognize app is available
            $appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
            if (!$appManager->isEnabledForUser('recognize')) {
                return [
                    'success' => true,
                    'message' => 'App Recognize não instalada — passo ignorado',
                    'details' => ['skipped' => true],
                ];
            }

            // Schedule the classify job via Nextcloud's job queue
            $jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);

            // Recognize uses scheduled jobs for classification — trigger a queue entry
            $jobClass = 'OCA\\Recognize\\BackgroundJobs\\ClassifyJob';
            if (!class_exists($jobClass)) {
                // Alternate class names across Recognize versions
                $jobClass = 'OCA\\Recognize\\BackgroundJobs\\SchedulerJob';
            }

            if (class_exists($jobClass)) {
                $jobList->add($jobClass, ['user' => $userId]);
                $this->logger->info('DevNull: ClassifyStep agendado', ['job' => $jobClass]);

                return [
                    'success' => true,
                    'message' => 'Classificação IA agendada (será executada pelo cron)',
                    'details' => ['scheduled' => true, 'job' => $jobClass],
                ];
            }

            // Recognize normally auto-classifies new files after scan.
            // If we can't find the job class, the scan step alone should trigger it.
            return [
                'success' => true,
                'message' => 'Recognize instalado — classificação será disparada automaticamente após scan',
                'details' => ['auto_trigger' => true],
            ];
        } catch (\Exception $e) {
            $this->logger->error('DevNull: ClassifyStep falhou', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Agendamento de classificação falhou: ' . $e->getMessage(),
            ];
        }
    }
}
