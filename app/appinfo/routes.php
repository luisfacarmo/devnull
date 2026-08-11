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

        // StatusReporting capability
        ['name' => 'Status#index', 'url' => '/api/v1/status', 'verb' => 'GET'],

        // Persistence (logs)
        ['name' => 'Operation#list', 'url' => '/api/v1/logs', 'verb' => 'GET'],

        // Daemon bridge (webhook receiver)
        ['name' => 'Daemon#event', 'url' => '/api/v1/daemon/event', 'verb' => 'POST'],
        ['name' => 'Daemon#getConfig', 'url' => '/api/v1/daemon/config', 'verb' => 'GET'],

        // Admin settings (automation rules)
        ['name' => 'Settings#get', 'url' => '/api/v1/settings', 'verb' => 'GET'],
        ['name' => 'Settings#update', 'url' => '/api/v1/settings', 'verb' => 'PUT'],
    ],
    'routes' => [
        // Frontend page
        ['name' => 'Page#index', 'url' => '/', 'verb' => 'GET'],
    ],
];
