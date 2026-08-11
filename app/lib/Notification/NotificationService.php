<?php

declare(strict_types=1);

namespace OCA\DevNull\Notification;

use OCA\DevNull\AppInfo\Application;
use OCP\IConfig;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Service to send Nextcloud notifications for DevNull events.
 *
 * Respects admin settings (notify_on_mount, notify_on_ingest_complete).
 */
class NotificationService
{
    public function __construct(
        private INotificationManager $notificationManager,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Notify user that a disk was mounted.
     */
    public function notifyMountComplete(string $userId, string $device, string $label, string $mountpoint): void
    {
        if ($this->config->getAppValue('devnull', 'notify_on_mount', 'true') !== 'true') {
            return;
        }

        $notification = $this->notificationManager->createNotification();
        $notification
            ->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setDateTime(new \DateTime())
            ->setObject('device', $device)
            ->setSubject('mount_complete', [
                'device' => $device,
                'label' => $label,
                'mountpoint' => $mountpoint,
            ]);

        $this->notificationManager->notify($notification);
        $this->logger->debug('DevNull: mount notification sent', ['user' => $userId, 'device' => $device]);
    }

    /**
     * Notify user that ingest pipeline completed.
     */
    public function notifyIngestComplete(string $userId, string $device, string $label, int $stepsCompleted): void
    {
        if ($this->config->getAppValue('devnull', 'notify_on_ingest_complete', 'true') !== 'true') {
            return;
        }

        $notification = $this->notificationManager->createNotification();
        $notification
            ->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setDateTime(new \DateTime())
            ->setObject('device', $device)
            ->setSubject('ingest_complete', [
                'device' => $device,
                'label' => $label,
                'steps_completed' => $stepsCompleted,
            ]);

        $this->notificationManager->notify($notification);
        $this->logger->debug('DevNull: ingest notification sent', ['user' => $userId, 'device' => $device]);
    }

    /**
     * Notify admin that a new disk was detected (hotplug).
     */
    public function notifyDiskAdded(string $userId, string $device, ?string $label): void
    {
        $notification = $this->notificationManager->createNotification();
        $notification
            ->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setDateTime(new \DateTime())
            ->setObject('device', $device)
            ->setSubject('disk_added', [
                'device' => $device,
                'label' => $label ?? $device,
            ]);

        $this->notificationManager->notify($notification);
    }
}
