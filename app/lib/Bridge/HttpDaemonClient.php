<?php

declare(strict_types=1);

namespace OCA\DevNull\Bridge;

use OCA\DevNull\Capability\DaemonClientInterface;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * HTTP-based daemon client.
 * Communicates with devnull-daemon via REST API.
 * Falls back gracefully on timeout/connection refused.
 */
class HttpDaemonClient implements DaemonClientInterface
{
    private const TIMEOUT = 2; // seconds
    private const DEFAULT_URL = 'http://127.0.0.1:9876';

    private string $baseUrl;

    public function __construct(
        private IClientService $httpClientService,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
        $this->baseUrl = $this->config->getAppValue(
            'devnull',
            'daemon_url',
            self::DEFAULT_URL
        );
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->request('GET', '/api/v1/health');
            return ($response['status'] ?? '') === 'healthy';
        } catch (\Exception) {
            return false;
        }
    }

    public function listDisks(): array
    {
        try {
            return $this->request('GET', '/api/v1/disks');
        } catch (\Exception $e) {
            $this->logger->warning('DevNull daemon unreachable: ' . $e->getMessage());
            return [];
        }
    }

    public function mount(string $device): array
    {
        try {
            return $this->request('POST', '/api/v1/mount', ['device' => $device]);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function unmount(string $device): array
    {
        try {
            return $this->request('POST', '/api/v1/unmount', ['device' => $device]);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function health(): array
    {
        try {
            return $this->request('GET', '/api/v1/health');
        } catch (\Exception) {
            return ['status' => 'offline'];
        }
    }

    /**
     * @throws \Exception on connection failure
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $client = $this->httpClientService->newClient();
        $url = $this->baseUrl . $path;

        $options = ['timeout' => self::TIMEOUT];
        if (!empty($body)) {
            $options['json'] = $body;
        }

        $response = match (strtoupper($method)) {
            'GET' => $client->get($url, $options),
            'POST' => $client->post($url, $options),
            default => throw new \InvalidArgumentException("Unsupported method: $method"),
        };

        return json_decode($response->getBody(), true) ?? [];
    }
}
