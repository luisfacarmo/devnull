<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest\Step;

use OCA\DevNull\Command\SecureCommandRunner;
use OCA\DevNull\Ingest\IngestStepInterface;
use Psr\Log\LoggerInterface;

/**
 * Scan step: runs occ files:scan to index files in Nextcloud.
 */
class ScanStep implements IngestStepInterface
{
    private const OCC_PATH = '/var/www/nextcloud/occ';

    public function __construct(
        private SecureCommandRunner $commandRunner,
        private LoggerInterface $logger,
    ) {
    }

    public function getId(): string
    {
        return 'scan';
    }

    public function getDescription(): string
    {
        return 'Escanear arquivos (indexar no Nextcloud)';
    }

    public function execute(string $mountpoint, string $userId): array
    {
        $this->logger->info('DevNull: ScanStep iniciado', [
            'mountpoint' => $mountpoint,
            'user' => $userId,
        ]);

        try {
            $output = $this->commandRunner->run('php', [
                self::OCC_PATH,
                'files:scan',
                '--path', '/' . $userId . '/files',
            ]);

            return [
                'success' => true,
                'message' => 'Scan concluído',
                'details' => ['output' => substr($output, 0, 500)],
            ];
        } catch (\RuntimeException $e) {
            $this->logger->error('DevNull: ScanStep falhou', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Scan falhou: ' . $e->getMessage(),
            ];
        }
    }
}
