<?php

declare(strict_types=1);

namespace OCA\DevNull\Notification;

use OCA\DevNull\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Nextcloud Notification handler for DevNull events.
 *
 * Formats notification messages for display in the NC notification panel.
 */
class Notifier implements INotifier
{
    public function __construct(
        private IL10N $l,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function getID(): string
    {
        return Application::APP_ID;
    }

    public function getName(): string
    {
        return $this->l->t('DevNull');
    }

    public function prepare(INotification $notification, string $languageCode): INotification
    {
        if ($notification->getApp() !== Application::APP_ID) {
            throw new UnknownNotificationException('Unknown app');
        }

        switch ($notification->getSubject()) {
            case 'mount_complete':
                $params = $notification->getSubjectParameters();
                $notification->setRichSubject(
                    $this->l->t('Disk {device} mounted successfully'),
                    [
                        'device' => [
                            'type' => 'highlight',
                            'id' => $params['device'] ?? 'unknown',
                            'name' => $params['label'] ?? $params['device'] ?? 'unknown',
                        ],
                    ]
                );
                $notification->setRichMessage(
                    $this->l->t('Mounted at {mountpoint} and registered in Nextcloud Files.'),
                    [
                        'mountpoint' => [
                            'type' => 'highlight',
                            'id' => 'mountpoint',
                            'name' => $params['mountpoint'] ?? '',
                        ],
                    ]
                );
                $notification->setIcon($this->urlGenerator->imagePath('devnull', 'app-dark.svg'));
                break;

            case 'ingest_complete':
                $params = $notification->getSubjectParameters();
                $notification->setRichSubject(
                    $this->l->t('Ingest pipeline completed for {device}'),
                    [
                        'device' => [
                            'type' => 'highlight',
                            'id' => $params['device'] ?? 'unknown',
                            'name' => $params['label'] ?? $params['device'] ?? 'unknown',
                        ],
                    ]
                );
                $steps = $params['steps_completed'] ?? 0;
                $notification->setParsedMessage(
                    $this->l->t('%d steps completed successfully.', [$steps])
                );
                $notification->setIcon($this->urlGenerator->imagePath('devnull', 'app-dark.svg'));
                break;

            case 'disk_added':
                $params = $notification->getSubjectParameters();
                $notification->setParsedSubject(
                    $this->l->t('New disk detected: %s', [$params['label'] ?? $params['device'] ?? 'unknown'])
                );
                $notification->setParsedMessage(
                    $this->l->t('A new external disk was connected to the server.')
                );
                $notification->setIcon($this->urlGenerator->imagePath('devnull', 'app-dark.svg'));
                break;

            default:
                throw new UnknownNotificationException('Unknown subject: ' . $notification->getSubject());
        }

        return $notification;
    }
}
