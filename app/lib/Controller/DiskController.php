<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCA\DevNull\Capability\DiskDetectorInterface;
use OCA\DevNull\Mount\MountStrategyFactory;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * API: Disk detection and listing.
 * Resolves detector lazily to prevent DI crashes.
 */
class DiskController extends OCSController
{
    public function __construct(
        IRequest $request,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * List available external disks.
     */
    public function list(): DataResponse
    {
        try {
            $detector = \OCP\Server::get(DiskDetectorInterface::class);
            $disks = $detector->listAvailable();

            return new DataResponse([
                'disks' => array_map(fn($d) => $d->toArray(), $disks),
                'capabilities' => [
                    'mount_available' => $this->isMountAvailable(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: disk detection failed', ['error' => $e->getMessage()]);
            return new DataResponse([
                'disks' => [],
                'capabilities' => ['mount_available' => false],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isMountAvailable(): bool
    {
        try {
            $factory = \OCP\Server::get(MountStrategyFactory::class);
            $factory->create();
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
