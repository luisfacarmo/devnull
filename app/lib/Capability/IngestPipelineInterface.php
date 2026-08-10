<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Capability: Ingest Pipeline
 *
 * Responsible for orchestrating post-mount processing steps (scan,
 * deduplicate, classify). Each step is independent and pluggable.
 *
 * Domain boundary: ONLY orchestrates processing steps. Never mounts,
 * detects, or registers storage.
 */
interface IngestPipelineInterface
{
    /**
     * Execute a pipeline of steps on a mounted disk.
     *
     * @param string $mountpoint Path to the mounted disk
     * @param array<string> $steps Step identifiers to execute in order
     * @param string $userId User who triggered the pipeline
     * @return string Operation ID for tracking progress
     */
    public function execute(
        string $mountpoint,
        array $steps,
        string $userId
    ): string;

    /**
     * Get available step identifiers.
     *
     * @return array<string, string> [id => description]
     */
    public function getAvailableSteps(): array;

    /**
     * Get progress of a running pipeline.
     *
     * @param string $operationId
     * @return PipelineStatus
     */
    public function getStatus(string $operationId): PipelineStatus;
}
