<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest;

/**
 * Interface for individual ingest pipeline steps.
 * Each step is independent and can be composed in any order.
 */
interface IngestStepInterface
{
    /**
     * Get the unique identifier for this step.
     */
    public function getId(): string;

    /**
     * Get human-readable description.
     */
    public function getDescription(): string;

    /**
     * Execute this step on a mountpoint.
     *
     * @param string $mountpoint Path to the mounted disk
     * @param string $userId User who triggered
     * @return array{success: bool, message: string, details?: array}
     */
    public function execute(string $mountpoint, string $userId): array;
}
