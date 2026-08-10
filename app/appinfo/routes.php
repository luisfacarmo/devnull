<?php

declare(strict_types=1);

/**
 * DevNull — Route definitions
 *
 * Routes follow capability domains:
 * - /disks     → DiskDetection capability
 * - /mount     → Mount capability
 * - /unmount   → Mount capability
 * - /ingest    → Ingest capability
 * - /status    → StatusReporting capability
 * - /logs      → Persistence (read-only)
 */
return [
    'ocs' => [
        // DiskDetection capability
        ['name' => 'Disk#list', 'url' => '/api/v1/disks', 'verb' => 'GET'],

        // Mount capability
        ['name' => 'Mount#mount', 'url' => '/api/v1/mount', 'verb' => 'POST'],
        ['name' => 'Mount#unmount', 'url' => '/api/v1/unmount', 'verb' => 'POST'],

        // Ingest capability
        ['name' => 'Ingest#start', 'url' => '/api/v1/ingest', 'verb' => 'POST'],
        ['name' => 'Ingest#getSteps', 'url' => '/api/v1/ingest/steps', 'verb' => 'GET'],
        ['name' => 'Ingest#getProgress', 'url' => '/api/v1/ingest/{operationId}', 'verb' => 'GET'],

        // StatusReporting capability
        ['name' => 'Status#index', 'url' => '/api/v1/status', 'verb' => 'GET'],

        // Persistence (logs)
        ['name' => 'Operation#list', 'url' => '/api/v1/logs', 'verb' => 'GET'],

        // Daemon bridge (health)
        ['name' => 'Daemon#health', 'url' => '/api/v1/daemon/health', 'verb' => 'GET'],
    ],
    'routes' => [
        // Frontend page
        ['name' => 'Page#index', 'url' => '/', 'verb' => 'GET'],
    ],
];
