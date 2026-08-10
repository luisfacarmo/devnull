<?php

declare(strict_types=1);

namespace OCA\DevNull\Detection;

use OCA\DevNull\Capability\DiskDetectorInterface;
use Psr\Log\LoggerInterface;

/**
 * Selects the best available disk detector based on priority and availability.
 * Implements P3 (Incremental Evolution): falls back gracefully.
 */
class DetectorFactory
{
    /** @var array<DiskDetectorInterface> */
    private array $detectors;

    public function __construct(
        private LoggerInterface $logger,
        DaemonBridgeDetector $daemonDetector,
        LsblkDetector $lsblkDetector,
    ) {
        $this->detectors = [$daemonDetector, $lsblkDetector];
    }

    /**
     * Get the highest-priority available detector.
     *
     * @throws \RuntimeException If no detector is available
     */
    public function create(): DiskDetectorInterface
    {
        // Sort by priority descending
        usort(
            $this->detectors,
            fn(DiskDetectorInterface $a, DiskDetectorInterface $b) =>
                $b->getPriority() <=> $a->getPriority()
        );

        foreach ($this->detectors as $detector) {
            if ($detector->isAvailable()) {
                $this->logger->debug(
                    'DevNull: using detector {class}',
                    ['class' => get_class($detector)]
                );
                return $detector;
            }
        }

        throw new \RuntimeException(
            'No disk detector available. Install util-linux or start the daemon.'
        );
    }
}
