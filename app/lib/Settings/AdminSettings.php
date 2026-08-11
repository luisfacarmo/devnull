<?php

declare(strict_types=1);

namespace OCA\DevNull\Settings;

use OCA\DevNull\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

/**
 * Admin settings page for DevNull automation rules.
 */
class AdminSettings implements ISettings
{
    public function __construct(
        private IConfig $config,
    ) {
    }

    public function getForm(): TemplateResponse
    {
        $params = [
            'auto_mount_on_plug' => $this->config->getAppValue('devnull', 'auto_mount_on_plug', 'false'),
            'auto_ingest_on_mount' => $this->config->getAppValue('devnull', 'auto_ingest_on_mount', 'false'),
            'auto_classify_on_scan' => $this->config->getAppValue('devnull', 'auto_classify_on_scan', 'true'),
            'notify_on_mount' => $this->config->getAppValue('devnull', 'notify_on_mount', 'true'),
            'notify_on_ingest_complete' => $this->config->getAppValue('devnull', 'notify_on_ingest_complete', 'true'),
            'default_mount_user' => $this->config->getAppValue('devnull', 'default_mount_user', 'admin'),
            'daemon_url' => $this->config->getAppValue('devnull', 'daemon_url', 'http://127.0.0.1:9876'),
            'daemon_token' => $this->config->getAppValue('devnull', 'daemon_token', ''),
        ];

        return new TemplateResponse(Application::APP_ID, 'settings/admin', $params);
    }

    public function getSection(): string
    {
        return 'devnull';
    }

    public function getPriority(): int
    {
        return 50;
    }
}
