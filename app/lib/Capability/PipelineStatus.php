<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Value object for pipeline execution status.
 */
final class PipelineStatus
{
    public function __construct(
        public readonly string $operationId,
        public readonly string $state, // pending, running, completed, failed
        public readonly int $progress, // 0-100
        public readonly ?string $currentStep,
        public readonly ?string $error,
        public readonly array $completedSteps,
    ) {
    }

    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'state' => $this->state,
            'progress' => $this->progress,
            'current_step' => $this->currentStep,
            'error' => $this->error,
            'completed_steps' => $this->completedSteps,
        ];
    }
}
