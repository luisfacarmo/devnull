<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest\Step;

use OCA\DevNull\Command\SecureCommandRunner;
use OCA\DevNull\Ingest\IngestStepInterface;
use Psr\Log\LoggerInterface;

/**
 * Deduplicate step: runs duplicatefinder to detect duplicate files.
 */
class DeduplicateStep implements IngestStepInterface
{
    private const OCC_PATH = '/var/www/nextcloud/occ';

    public function __construct(
        private SecureCommandRunner $commandRunner,
        private LoggerInterface $logger,
    ) {
    }

    public function getId(): string
    {
        return 'dedup';
    }

    public function getDescription(): string
    {
        return 'Detectar duplicatas';
    }

    public function execute(string $mountpoint, string $userId): array
    {
        $this->logger->info('DevNull: DeduplicateStep iniciado', [
            'mountpoint' => $mountpoint,
            'user' => $userId,
        ]);

        try {
            $output = $this->commandRunner->run('php', [
                self::OCC_PATH,
                'duplicatefinder:find-all',
            ]);

            return [
                'success' => true,
                'message' => 'Detecção de duplicatas concluída',
                'details' => ['output' => substr($output, 0, 500)],
            ];
        } catch (\RuntimeException $e) {
            $this->logger->error('DevNull: DeduplicateStep falhou', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Deduplicação falhou: ' . $e->getMessage(),
            ];
        }
    }
}
