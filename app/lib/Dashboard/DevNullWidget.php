<?php

declare(strict_types=1);

namespace OCA\DevNull\Dashboard;

use OCA\DevNull\AppInfo\Application;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Dashboard widget for DevNull.
 *
 * Shows:
 * - Currently mounted disks
 * - Recent operations (mount, eject, ingest)
 *
 * Uses IAPIWidgetV2 — no frontend JavaScript needed, NC renders it natively.
 * Reloads every 30 seconds via IReloadableWidget.
 */
class DevNullWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget
{
    public function __construct(
        private IL10N $l,
        private IURLGenerator $urlGenerator,
        private IDBConnection $db,
    ) {
    }

    public function getId(): string
    {
        return 'devnull';
    }

    public function getTitle(): string
    {
        return $this->l->t('DevNull — Discos');
    }

    public function getOrder(): int
    {
        return 30;
    }

    public function getIconClass(): string
    {
        return 'icon-external';
    }

    public function getIconUrl(): string
    {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
        );
    }

    public function getUrl(): ?string
    {
        return $this->urlGenerator->linkToRouteAbsolute('devnull.page.index');
    }

    public function load(): void
    {
        // No JS needed — IAPIWidgetV2 renders server-side
    }

    public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems
    {
        $items = [];

        // Show active mounts
        $mounts = $this->getActiveMounts();
        foreach ($mounts as $mount) {
            $items[] = new WidgetItem(
                $mount['label'] ?? 'Disco',
                $this->l->t('Montado em %s', [$mount['mountpoint'] ?? '?']),
                $this->urlGenerator->linkToRouteAbsolute('devnull.page.index'),
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')),
                $mount['mounted_at'] ?? '',
            );
        }

        // Fill remaining slots with recent operations
        $remaining = $limit - count($items);
        if ($remaining > 0) {
            $operations = $this->getRecentOperations($userId, $remaining);
            foreach ($operations as $op) {
                $subtitle = $this->formatOperationType($op['type']) . ' — ' . $this->formatStatus($op['status']);
                $items[] = new WidgetItem(
                    $subtitle,
                    $op['started_at'] ?? '',
                    $this->urlGenerator->linkToRouteAbsolute('devnull.page.index'),
                    $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')),
                    $op['started_at'] ?? '',
                );
            }
        }

        $emptyMessage = empty($items)
            ? $this->l->t('Nenhum disco conectado. Espete um HD externo para começar.')
            : '';

        return new WidgetItems($items, $emptyMessage);
    }

    public function getWidgetButtons(string $userId): array
    {
        return [
            new WidgetButton(
                WidgetButton::TYPE_MORE,
                $this->urlGenerator->linkToRouteAbsolute('devnull.page.index'),
                $this->l->t('Abrir DevNull'),
            ),
        ];
    }

    public function getReloadInterval(): int
    {
        return 30;
    }

    private function getActiveMounts(): array
    {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from('devnull_mounts')
                ->setMaxResults(5);
            $result = $qb->executeQuery();
            $rows = $result->fetchAll();
            $result->closeCursor();
            return $rows;
        } catch (\Exception) {
            return [];
        }
    }

    private function getRecentOperations(string $userId, int $limit): array
    {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from('devnull_operations')
                ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
                ->orderBy('started_at', 'DESC')
                ->setMaxResults($limit);
            $result = $qb->executeQuery();
            $rows = $result->fetchAll();
            $result->closeCursor();
            return $rows;
        } catch (\Exception) {
            return [];
        }
    }

    private function formatOperationType(string $type): string
    {
        return match ($type) {
            'mount' => $this->l->t('Montou'),
            'unmount' => $this->l->t('Ejetou'),
            'ingest' => $this->l->t('Pipeline'),
            'scan' => $this->l->t('Scan'),
            default => $type,
        };
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'completed' => $this->l->t('concluído'),
            'failed' => $this->l->t('falhou'),
            'running' => $this->l->t('em andamento'),
            default => $status,
        };
    }
}
