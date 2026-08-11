<?php

declare(strict_types=1);

namespace OCA\DevNull\Controller;

use OCA\DevNull\AppInfo\Application;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;

/**
 * API: Admin settings for automation rules.
 *
 * All methods require admin (no @NoAdminRequired).
 */
class SettingsController extends OCSController
{
    private const ALLOWED_KEYS = [
        'auto_mount_on_plug',
        'auto_ingest_on_mount',
        'auto_classify_on_scan',
        'notify_on_mount',
        'notify_on_ingest_complete',
        'default_mount_user',
        'daemon_url',
        'daemon_token',
    ];

    public function __construct(
        IRequest $request,
        private IConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get all automation settings.
     *
     * @return DataResponse
     */
    public function get(): DataResponse
    {
        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $settings[$key] = $this->config->getAppValue('devnull', $key, $this->getDefault($key));
        }
        return new DataResponse(['success' => true, 'settings' => $settings]);
    }

    /**
     * Update automation settings.
     *
     * @param array $settings Key-value pairs to update
     * @return DataResponse
     */
    public function update(array $settings): DataResponse
    {
        $updated = [];
        foreach ($settings as $key => $value) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }
            // Sanitize boolean values
            if (in_array($key, ['auto_mount_on_plug', 'auto_ingest_on_mount', 'auto_classify_on_scan', 'notify_on_mount', 'notify_on_ingest_complete'], true)) {
                $value = ($value === true || $value === 'true' || $value === '1') ? 'true' : 'false';
            }
            $this->config->setAppValue('devnull', $key, (string) $value);
            $updated[$key] = $value;
        }

        return new DataResponse(['success' => true, 'updated' => $updated]);
    }

    private function getDefault(string $key): string
    {
        return match ($key) {
            'auto_mount_on_plug' => 'false',
            'auto_ingest_on_mount' => 'false',
            'auto_classify_on_scan' => 'true',
            'notify_on_mount' => 'true',
            'notify_on_ingest_complete' => 'true',
            'default_mount_user' => 'admin',
            'daemon_url' => 'http://127.0.0.1:9876',
            'daemon_token' => '',
            default => '',
        };
    }
}
