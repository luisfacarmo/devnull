<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest\Step;

use OCA\DevNull\Ingest\IngestStepInterface;
use Psr\Log\LoggerInterface;

/**
 * Scan step: indexes files in Nextcloud using the internal Scanner API.
 *
 * Uses \OC\Files\Utils\Scanner directly (same engine as `occ files:scan`)
 * instead of spawning a subprocess. This works within HTTP context.
 */
class ScanStep implements IngestStepInterface
{
    public function __construct(
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
            // Resolve IUser object (NC 34+ Scanner requires IUser, not string)
            $userManager = \OCP\Server::get(\OCP\IUserManager::class);
            $user = $userManager->get($userId);
            if ($user === null) {
                return [
                    'success' => false,
                    'message' => 'Usuário não encontrado: ' . $userId,
                ];
            }

            // Setup user filesystem (required before scanning)
            \OC_Util::setupFS($userId);

            // Scan the user's full files tree (covers the mounted external storage)
            $scanPath = '/' . $userId . '/files';

            $scanner = new \OC\Files\Utils\Scanner(
                $user,
                \OCP\Server::get(\OCP\IDBConnection::class),
                \OCP\Server::get(\OCP\EventDispatcher\IEventDispatcher::class),
                $this->logger,
                \OCP\Server::get(\OC\Files\SetupManager::class),
            );

            $scanner->scan($scanPath, $recursive = true, null);

            $this->logger->info('DevNull: ScanStep concluído', ['user' => $userId]);

            // Auto-trigger Recognize classification if enabled
            $this->triggerAutoClassify($userId);

            return [
                'success' => true,
                'message' => 'Scan concluído',
                'details' => ['path' => $scanPath],
            ];
        } catch (\Exception $e) {
            $this->logger->error('DevNull: ScanStep falhou', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Scan falhou: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Automatically schedule Recognize classification after scan if enabled.
     *
     * Respects the admin setting `auto_classify_on_scan`.
     * Uses IJobList to schedule Recognize's background job.
     */
    private function triggerAutoClassify(string $userId): void
    {
        try {
            $config = \OCP\Server::get(\OCP\IConfig::class);
            if ($config->getAppValue('devnull', 'auto_classify_on_scan', 'true') !== 'true') {
                return;
            }

            // Check if Recognize is available
            $appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
            if (!$appManager->isEnabledForUser('recognize')) {
                return;
            }

            // Schedule Recognize classification job
            $jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);

            $jobClass = 'OCA\\Recognize\\BackgroundJobs\\ClassifyJob';
            if (!class_exists($jobClass)) {
                $jobClass = 'OCA\\Recognize\\BackgroundJobs\\SchedulerJob';
            }

            if (class_exists($jobClass)) {
                $jobList->add($jobClass, ['user' => $userId]);
                $this->logger->info('DevNull: auto-classify scheduled after scan', [
                    'user' => $userId,
                    'job' => $jobClass,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->debug('DevNull: auto-classify trigger failed (non-critical)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
