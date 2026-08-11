<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest\Step;

use OCA\DevNull\Ingest\IngestStepInterface;
use Psr\Log\LoggerInterface;

/**
 * Deduplicate step: schedules duplicate detection as a BackgroundJob.
 *
 * Cannot call `occ duplicatefinder:find-all` from HTTP context.
 * Instead, adds a one-time job to Nextcloud's job queue.
 * The actual dedup runs asynchronously via cron.
 */
class DeduplicateStep implements IngestStepInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function getId(): string
    {
        return 'dedup';
    }

    public function getDescription(): string
    {
        return 'Detectar duplicatas (agendado em background)';
    }

    public function execute(string $mountpoint, string $userId): array
    {
        $this->logger->info('DevNull: DeduplicateStep iniciado', [
            'mountpoint' => $mountpoint,
            'user' => $userId,
        ]);

        try {
            // Check if duplicatefinder app is available
            $appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
            if (!$appManager->isEnabledForUser('duplicatefinder')) {
                return [
                    'success' => true,
                    'message' => 'App duplicatefinder não instalada — passo ignorado',
                    'details' => ['skipped' => true],
                ];
            }

            // Schedule the dedup job via Nextcloud's job queue
            $jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);

            // Use the duplicatefinder's own background job class if available
            $jobClass = 'OCA\\DuplicateFinder\\BackgroundJob\\FindDuplicates';
            if (!class_exists($jobClass)) {
                // Fallback: some versions use different class name
                $jobClass = 'OCA\\DuplicateFinder\\BackgroundJob\\FindDuplicatesJob';
            }

            if (class_exists($jobClass)) {
                $jobList->add($jobClass, ['user' => $userId]);
                $this->logger->info('DevNull: DeduplicateStep agendado', ['job' => $jobClass]);

                return [
                    'success' => true,
                    'message' => 'Detecção de duplicatas agendada (será executada pelo cron)',
                    'details' => ['scheduled' => true, 'job' => $jobClass],
                ];
            }

            // If no known job class exists, report gracefully
            return [
                'success' => true,
                'message' => 'Duplicatefinder instalado mas job class não encontrada — execute manualmente: occ duplicatefinder:find-all',
                'details' => ['manual_required' => true],
            ];
        } catch (\Exception $e) {
            $this->logger->error('DevNull: DeduplicateStep falhou', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Agendamento de dedup falhou: ' . $e->getMessage(),
            ];
        }
    }
}
