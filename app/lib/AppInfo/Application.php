<?php

declare(strict_types=1);

namespace OCA\DevNull\AppInfo;

use OCA\DevNull\Bridge\HttpDaemonClient;
use OCA\DevNull\Bridge\NullDaemonClient;
use OCA\DevNull\Capability\DaemonClientInterface;
use OCA\DevNull\Capability\DiskDetectorInterface;
use OCA\DevNull\Capability\StatusTransportInterface;
use OCA\DevNull\Capability\StorageRegistrarInterface;
use OCA\DevNull\Detection\DetectorFactory;
use OCA\DevNull\Detection\LsblkDetector;
use OCA\DevNull\Event\DiskMountedEvent;
use OCA\DevNull\Event\DiskUnmountedEvent;
use OCA\DevNull\Event\IngestCompletedEvent;
use OCA\DevNull\Listener\LogOnUnmount;
use OCA\DevNull\Listener\NotifyOnIngestComplete;
use OCA\DevNull\Listener\TriggerScanOnMount;
use OCA\DevNull\Status\PollingTransport;
use OCA\DevNull\Storage\NextcloudStorageRegistrar;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'devnull';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        // DaemonClient: try HTTP, fallback to Null (never throws)
        $context->registerService(DaemonClientInterface::class, function ($c) {
            try {
                $httpClient = $c->get(HttpDaemonClient::class);
                if ($httpClient->isAvailable()) {
                    return $httpClient;
                }
            } catch (\Exception) {
                // Daemon not configured
            }
            return new NullDaemonClient();
        });

        // DiskDetector: try factory, fallback to LsblkDetector directly
        $context->registerService(DiskDetectorInterface::class, function ($c) {
            try {
                return $c->get(DetectorFactory::class)->create();
            } catch (\Exception) {
                // Fallback to direct LsblkDetector
                return $c->get(LsblkDetector::class);
            }
        });

        // StorageRegistrar: always available (just calls occ)
        $context->registerService(StorageRegistrarInterface::class, function ($c) {
            return $c->get(NextcloudStorageRegistrar::class);
        });

        // StatusTransport: always available
        $context->registerService(StatusTransportInterface::class, function ($c) {
            return $c->get(PollingTransport::class);
        });

        // Event listeners
        $context->registerEventListener(DiskMountedEvent::class, TriggerScanOnMount::class);
        $context->registerEventListener(DiskUnmountedEvent::class, LogOnUnmount::class);
        $context->registerEventListener(IngestCompletedEvent::class, NotifyOnIngestComplete::class);
    }

    public function boot(IBootContext $context): void
    {
        // No boot-time logic needed
    }
}
