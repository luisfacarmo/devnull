<?php

declare(strict_types=1);

namespace OCA\DevNull\Ingest;

use OCA\DevNull\Ingest\Step\ClassifyStep;
use OCA\DevNull\Ingest\Step\DeduplicateStep;
use OCA\DevNull\Ingest\Step\ScanStep;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates ingest pipeline steps in sequence.
 * Steps are independent — if one fails, the next still runs.
 */
class IngestPipeline
{
    /** @var array<IngestStepInterface> */
    private array $availableSteps;

    public function __construct(
        private ScanStep $scanStep,
        private DeduplicateStep $deduplicateStep,
        private ClassifyStep $classifyStep,
        private LoggerInterface $logger,
    ) {
        $this->availableSteps = [
            $scanStep->getId() => $scanStep,
            $deduplicateStep->getId() => $deduplicateStep,
            $classifyStep->getId() => $classifyStep,
        ];
    }

    /**
     * Get all available step identifiers and descriptions.
     *
     * @return array<string, string> [id => description]
     */
    public function getAvailableSteps(): array
    {
        $steps = [];
        foreach ($this->availableSteps as $id => $step) {
            $steps[$id] = $step->getDescription();
        }
        return $steps;
    }

    /**
     * Execute a sequence of steps on a mountpoint.
     *
     * @param string $mountpoint
     * @param array<string> $stepIds Steps to execute (in order)
     * @param string $userId
     * @return array{success: bool, results: array}
     */
    public function execute(string $mountpoint, array $stepIds, string $userId): array
    {
        $this->logger->info('DevNull: Pipeline iniciado', [
            'mountpoint' => $mountpoint,
            'steps' => $stepIds,
            'user' => $userId,
        ]);

        $results = [];
        $allSuccess = true;

        foreach ($stepIds as $stepId) {
            if (!isset($this->availableSteps[$stepId])) {
                $results[$stepId] = [
                    'success' => false,
                    'message' => "Step desconhecido: $stepId",
                ];
                $allSuccess = false;
                continue;
            }

            $step = $this->availableSteps[$stepId];
            $result = $step->execute($mountpoint, $userId);
            $results[$stepId] = $result;

            if (!$result['success']) {
                $allSuccess = false;
                $this->logger->warning('DevNull: Step falhou, continuando pipeline', [
                    'step' => $stepId,
                    'error' => $result['message'],
                ]);
            }
        }

        $this->logger->info('DevNull: Pipeline concluído', [
            'success' => $allSuccess,
            'mountpoint' => $mountpoint,
        ]);

        return [
            'success' => $allSuccess,
            'results' => $results,
        ];
    }
}
