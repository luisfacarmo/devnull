<?php

declare(strict_types=1);

namespace OCA\DevNull\Mount;

use OCA\DevNull\Capability\MountStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Selects the best available mount strategy.
 * Priority-based: highest available wins.
 */
class MountStrategyFactory
{
    /** @var array<MountStrategyInterface> */
    private array $strategies;

    public function __construct(
        private LoggerInterface $logger,
        UdisksMountStrategy $udisks,
        SudoMountStrategy $sudo,
    ) {
        $this->strategies = [$udisks, $sudo];
    }

    public function create(): MountStrategyInterface
    {
        usort(
            $this->strategies,
            fn(MountStrategyInterface $a, MountStrategyInterface $b) =>
                $b->getPriority() <=> $a->getPriority()
        );

        foreach ($this->strategies as $strategy) {
            if ($strategy->isAvailable()) {
                $this->logger->debug(
                    'DevNull: using mount strategy {class}',
                    ['class' => get_class($strategy)]
                );
                return $strategy;
            }
        }

        throw new \RuntimeException(
            'No mount strategy available. Install udisks2 or configure sudoers.'
        );
    }
}
