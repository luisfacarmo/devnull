<?php

declare(strict_types=1);

namespace OCA\DevNull\BackgroundJob;

use OCP\BackgroundJob\QueuedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * Background job: scan a newly mounted external storage.
 *
 * Runs via the Nextcloud cron (web or system) so the VFS has already
 * mounted the storage by the time the scan executes — avoiding the
 * timing issue where a synchronous scan returns 0 files because the
 * storage is not yet visible in the same HTTP request that registered it.
 *
 * Arguments (passed to run()):
 *   - userId     (string) Nextcloud user who mounted the disk
 *   - mountpoint (string) Filesystem path, e.g. /media/www-data/REPLY
 *   - label      (string) Storage mount point label, e.g. REPLY
 */
class ScanMountedStorage extends QueuedJob
{
    public function __construct(
        ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
    }

    protected function run(mixed $argument): void
    {
        $userId     = $argument['userId'] ?? '';
        $mountpoint = $argument['mountpoint'] ?? '';
        $label      = $argument['label'] ?? basename(rtrim($mountpoint, '/'));

        if ($userId === '' || $mountpoint === '') {
            $this->logger->warning('DevNull ScanMountedStorage: missing arguments', $argument);
            return;
        }

        $this->logger->info('DevNull ScanMountedStorage: starting', [
            'user'       => $userId,
            'mountpoint' => $mountpoint,
            'label'      => $label,
        ]);

        try {
            $userManager = \OCP\Server::get(\OCP\IUserManager::class);
            $user = $userManager->get($userId);
            if ($user === null) {
                $this->logger->warning('DevNull ScanMountedStorage: user not found', ['user' => $userId]);
                return;
            }

            \OC_Util::setupFS($userId);

            // Scan only the specific external storage path
            $scanPath = '/' . $userId . '/files/' . $label;

            $scanner = new \OC\Files\Utils\Scanner(
                $user,
                \OCP\Server::get(\OCP\IDBConnection::class),
                \OCP\Server::get(\OCP\EventDispatcher\IEventDispatcher::class),
                $this->logger,
                \OCP\Server::get(\OC\Files\SetupManager::class),
            );

            $scanner->scan($scanPath, $recursive = true, null);

            $this->logger->info('DevNull ScanMountedStorage: completed', [
                'user' => $userId,
                'path' => $scanPath,
            ]);

            // Schedule Recognize classification if enabled
            $this->triggerAutoClassify($userId);
        } catch (\Exception $e) {
            $this->logger->error('DevNull ScanMountedStorage: failed', [
                'error' => $e->getMessage(),
                'user'  => $userId,
                'path'  => $mountpoint,
            ]);
        }
    }

    private function triggerAutoClassify(string $userId): void
    {
        try {
            $config = \OCP\Server::get(\OCP\IConfig::class);
            if ($config->getAppValue('devnull', 'auto_classify_on_scan', 'true') !== 'true') {
                return;
            }
            $appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
            if (!$appManager->isEnabledForUser('recognize')) {
                return;
            }
            $jobList  = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);
            $jobClass = 'OCA\\Recognize\\BackgroundJobs\\ClassifyJob';
            if (!class_exists($jobClass)) {
                $jobClass = 'OCA\\Recognize\\BackgroundJobs\\SchedulerJob';
            }
            if (class_exists($jobClass)) {
                $jobList->add($jobClass, ['user' => $userId]);
            }
        } catch (\Exception) {
            // Non-critical
        }
    }
}
