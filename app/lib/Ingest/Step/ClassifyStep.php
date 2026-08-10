<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest\Step;

use OCA\DevNull\Command\SecureCommandRunner;
use OCA\DevNull\Ingest\IngestStepInterface;
use Psr\Log\LoggerInterface;

/**
 * Classify step: runs Recognize to tag photos/videos with AI.
 */
class ClassifyStep implements IngestStepInterface
{
    private const OCC_PATH = '/var/www/nextcloud/occ';

    public function __construct(
        private SecureCommandRunner $commandRunner,
        private LoggerInterface $logger,
    ) {
    }

    public function getId(): string
    {
        return 'classify';
    }

    public function getDescription(): string
    {
        return 'Classificar mídia com IA (Recognize)';
    }

    public function execute(string $mountpoint, string $userId): array
    {
        $this->logger->info('DevNull: ClassifyStep iniciado', [
            'mountpoint' => $mountpoint,
            'user' => $userId,
        ]);

        try {
            $output = $this->commandRunner->run('php', [
                self::OCC_PATH,
                'recognize:classify',
            ]);

            return [
                'success' => true,
                'message' => 'Classificação IA concluída',
                'details' => ['output' => substr($output, 0, 500)],
            ];
        } catch (\RuntimeException $e) {
            $this->logger->error('DevNull: ClassifyStep falhou', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Classificação falhou: ' . $e->getMessage(),
            ];
        }
    }
}
