<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCA\DevNull\Ingest\IngestPipeline;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * API: Ingest pipeline operations (scan, dedup, classify).
 *
 * Minimal DI — only logger injected. Pipeline resolved lazily.
 */
class IngestController extends OCSController
{
    public function __construct(
        IRequest $request,
        private LoggerInterface $logger,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Start an ingest pipeline on a mounted disk.
     *
     * @param string $device Device name (e.g., "sdb1")
     * @param array $steps Steps to run (default: all)
     * @return DataResponse
     */
    public function start(string $device, array $steps = []): DataResponse
    {
        if (!preg_match('/^[a-z0-9]+$/', $device)) {
            return new DataResponse([
                'success' => false,
                'error' => 'Nome de dispositivo inválido',
                'code' => 'INVALID_DEVICE',
            ], 400);
        }

        if ($this->userId === null) {
            return new DataResponse([
                'success' => false,
                'error' => 'Usuário não autenticado',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        try {
            // Find mountpoint from disk detector
            $detector = \OCP\Server::get(\OCA\DevNull\Capability\DiskDetectorInterface::class);
            $disks = $detector->listAvailable();
            $mountpoint = null;

            foreach ($disks as $disk) {
                if ($disk->name === $device && $disk->mountpoint) {
                    $mountpoint = $disk->mountpoint;
                    break;
                }
            }

            if ($mountpoint === null) {
                return new DataResponse([
                    'success' => false,
                    'error' => 'Disco não está montado',
                    'code' => 'DEVICE_NOT_MOUNTED',
                ], 404);
            }

            // Get pipeline
            $pipeline = \OCP\Server::get(IngestPipeline::class);

            // Default: all steps
            if (empty($steps)) {
                $steps = array_keys($pipeline->getAvailableSteps());
            }

            $this->logger->info('DevNull: Pipeline iniciado', [
                'device' => $device,
                'mountpoint' => $mountpoint,
                'steps' => $steps,
            ]);

            // Execute
            $result = $pipeline->execute($mountpoint, $steps, $this->userId);

            return new DataResponse([
                'success' => $result['success'],
                'results' => $result['results'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DevNull: Pipeline falhou', ['error' => $e->getMessage()]);
            return new DataResponse([
                'success' => false,
                'error' => 'Pipeline falhou: ' . $e->getMessage(),
                'code' => 'PIPELINE_ERROR',
            ], 500);
        }
    }

    /**
     * Get available pipeline steps.
     *
     * @NoAdminRequired
     * @return DataResponse
     */
    public function getSteps(): DataResponse
    {
        try {
            $pipeline = \OCP\Server::get(IngestPipeline::class);
            return new DataResponse(['steps' => $pipeline->getAvailableSteps()]);
        } catch (\Exception $e) {
            return new DataResponse(['steps' => [], 'error' => $e->getMessage()]);
        }
    }
}
