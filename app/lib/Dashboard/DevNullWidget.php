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
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $logger,
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
        // oc_devnull_mounts is only populated by the daemon path; when the
        // PHP-side MountController is used it stores data in oc_external_mounts
        // (via files_external) and oc_appconfig. We read from oc_external_mounts
        // joined with oc_external_config so the widget always reflects the real
        // registered storages.
        //
        // Schema:
        //   oc_external_mounts  → mount_id, mount_point
        //   oc_external_config  → mount_id, key, value  (key='datadir' → path)
        //   oc_external_applicable → mount_id, type, value (type=3 → user)
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('m.mount_id', 'm.mount_point', 'c.value AS datadir')
                ->from('external_mounts', 'm')
                ->leftJoin('m', 'external_config', 'c',
                    $qb->expr()->andX(
                        $qb->expr()->eq('c.mount_id', 'm.mount_id'),
                        $qb->expr()->eq('c.key', $qb->createNamedParameter('datadir'))
                    )
                )
                ->setMaxResults(10);
            $result = $qb->executeQuery();
            $rows = $result->fetchAll();
            $result->closeCursor();

            $mounts = [];
            foreach ($rows as $row) {
                $datadir = rtrim($row['datadir'] ?? '', '/');

                if ($datadir === '') {
                    continue;
                }

                // Only show storages managed by devnull (under /media/)
                if (!str_contains($datadir, '/media/')) {
                    continue;
                }

                // Only show if path actually exists on disk right now
                if (!is_dir($datadir)) {
                    continue;
                }

                $label = $row['mount_point'] ?? basename($datadir);
                // Strip leading slash from mount_point (e.g. "/BÁRBARA" → "BÁRBARA")
                $label = ltrim($label, '/');

                $mountedAt = '';
                // Try to read our .devnull marker for mounted_at metadata
                $marker = $datadir . '/.devnull';
                if (is_readable($marker)) {
                    $meta = json_decode((string) @file_get_contents($marker), true);
                    $mountedAt = $meta['mounted_at'] ?? '';
                }

                $mounts[] = [
                    'label'      => $label,
                    'mountpoint' => $datadir,
                    'mounted_at' => $mountedAt,
                ];
            }

            return $mounts;
        } catch (\Exception $e) {
            $this->logger->debug('DevNull widget: getActiveMounts failed', ['error' => $e->getMessage()]);
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
