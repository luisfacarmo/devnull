<?php

declare(strict_types=1);

namespace OCA\DevNull\AppInfo;

use OCA\DevNull\Bridge\HttpDaemonClient;
use OCA\DevNull\Bridge\NullDaemonClient;
use OCA\DevNull\Capability\DaemonClientInterface;
use OCA\DevNull\Capability\DiskDetectorInterface;
use OCA\DevNull\Capability\MountStrategyInterface;
use OCA\DevNull\Capability\StorageRegistrarInterface;
use OCA\DevNull\Detection\DetectorFactory;
use OCA\DevNull\Event\DiskMountedEvent;
use OCA\DevNull\Event\DiskUnmountedEvent;
use OCA\DevNull\Event\IngestCompletedEvent;
use OCA\DevNull\Listener\LogOnUnmount;
use OCA\DevNull\Listener\NotifyOnIngestComplete;
use OCA\DevNull\Listener\TriggerScanOnMount;
use OCA\DevNull\Mount\MountStrategyFactory;
use OCA\DevNull\Capability\StatusTransportInterface;
use OCA\DevNull\Status\PollingTransport;
use OCA\DevNull\Storage\NextcloudStorageRegistrar;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'devnull';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        // Capability bindings — controllers depend on interfaces, not classes
        $context->registerService(DaemonClientInterface::class, function ($c) {
            $httpClient = $c->get(HttpDaemonClient::class);
            if ($httpClient->isAvailable()) {
                return $httpClient;
            }
            return $c->get(NullDaemonClient::class);
        });

        $context->registerService(DiskDetectorInterface::class, function ($c) {
            return $c->get(DetectorFactory::class)->create();
        });

        $context->registerService(MountStrategyInterface::class, function ($c) {
            // Lazy: don't resolve strategy at registration time.
            // Return a lazy proxy that resolves on first use.
            // This prevents crashes when udisks2 is not installed.
            try {
                return $c->get(MountStrategyFactory::class)->create();
            } catch (\RuntimeException $e) {
                // Fallback: return a NullMountStrategy that returns errors gracefully
                return new \OCA\DevNull\Mount\NullMountStrategy();
            }
        });

        $context->registerService(StorageRegistrarInterface::class, function ($c) {
            return $c->get(NextcloudStorageRegistrar::class);
        });

        $context->registerService(StatusTransportInterface::class, function ($c) {
            return $c->get(PollingTransport::class);
        });

        // Event listeners (P2: Domain Separation via events)
        $context->registerEventListener(
            DiskMountedEvent::class,
            TriggerScanOnMount::class
        );
        $context->registerEventListener(
            DiskUnmountedEvent::class,
            LogOnUnmount::class
        );
        $context->registerEventListener(
            IngestCompletedEvent::class,
            NotifyOnIngestComplete::class
        );
    }

    public function boot(IBootContext $context): void
    {
        // Boot-time logic (if needed)
    }
}
