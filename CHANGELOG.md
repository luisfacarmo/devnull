# Changelog

All notable changes to DevNull will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.5.0] — 2026-08-11

### Added
- **Admin settings page** — automation rules configurable from NC admin panel
  - auto_mount_on_plug, auto_ingest_on_mount, auto_classify_on_scan
  - notify_on_mount, notify_on_ingest_complete
  - daemon_url, daemon_token, default_mount_user
  - OCS API: GET/PUT `/api/v1/settings`
- **Nextcloud notifications** — push notifications in NC notification panel
  - Disk mounted (device, mountpoint)
  - Ingest pipeline completed (steps count)
  - New disk detected (hotplug via daemon)
  - Respects admin settings (can be toggled off)
- **Auto-classify trigger** — after scan completes, automatically schedules Recognize ClassifyJob via IJobList (configurable via `auto_classify_on_scan`)
- **Activity dashboard** — enhanced OperationLog Vue component
  - Stats summary (total/completed/failed/running)
  - Type and status filters
  - Auto-refresh every 30s
  - Duration column
  - Type icons and empty state hints

### Changed
- SettingsController registered in routes (GET/PUT `/api/v1/settings`)
- AdminSection + AdminSettings registered in info.xml for NC admin panel
- Notifier registered in Application.php via `registerNotifierService`
- MountController sends notification after successful mount
- IngestController sends notification after pipeline completion

## [0.4.0] — 2026-08-11

### Added
- **Python daemon** (`daemon/`) — standalone service for hardware detection and mount
  - FastAPI REST API on port 9876 (configurable)
  - Hotplug detection via pyudev (real-time) or lsblk polling (fallback)
  - Auto-mount on plug (optional, configurable)
  - Webhook notifier: calls Nextcloud app on disk_added/disk_removed
  - SSE `/api/v1/events` endpoint for real-time frontend push
  - Event history with replay on reconnection
  - Shared token auth (X-DevNull-Token)
  - systemd service unit for production deployment
- **DaemonController** (PHP) — webhook receiver for daemon events
  - POST `/api/v1/daemon/event` — receives hotplug notifications
  - GET `/api/v1/daemon/config` — daemon self-configuration
  - Auto-registers external storage when daemon reports mounted disk
  - Auto-removes external storage when daemon reports disk unplugged
  - Token auth via X-DevNull-Token (no NC session required)
- **HttpDaemonClient** now sends auth token on all requests to daemon

### Changed
- Daemon mount strategies check lsblk before calling udisksctl (prevents AlreadyMounted errors)
- Daemon parses real mountpoint from udisksctl stdout (handles UTF-8 labels)
- Version bump: app 0.4.0, daemon 0.4.0

## [0.3.0] — 2026-08-10

### Fixed
- **P1 (Scan/Indexação):** `scanUserFiles()` now uses `\OC\Files\Utils\Scanner` directly — content appears in Nextcloud Files after mount without manual `occ files:scan`
- **P2 (Eject lifecycle):** storageId saved in `oc_appconfig` on mount; eject removes external storage by ID instead of re-detecting unmounted disk
- **P3 (Pipeline):** ingest steps no longer call `occ` as subprocess. ScanStep uses PHP Scanner API; DeduplicateStep/ClassifyStep schedule via `IJobList` (background jobs)
- **P4 (DB migration):** documented force-migration procedure (disable/enable cycle)

### Added
- `@NoAdminRequired` on read-only endpoints (disk list, steps, status, logs) — non-admin users can access the UI
- PHPStan config (level 5) with `phpstan.neon.dist`
- Consistent error responses: all endpoints return `{success, error?, code?}` with HTTP status codes
- Null guards for `$userId` across controllers (type safety)
- Device name validation (`/^[a-z0-9]+$/`) in IngestController

### Changed
- Removed `php` from `SecureCommandRunner` allowed commands whitelist (security hardening)
- OperationController: capped `limit` param to 100 max (resource protection)
- IngestPipeline: improved PHPDoc type annotations

### Removed
- `StatusTransportInterface` (unused, no implementations)
- Empty directories: `lib/Db/Entity/`, `lib/Db/Mapper/`, `lib/Status/`

## [0.2.0] — 2026-08-09

### Added
- Disk detection via lsblk (recursive, filters system devices)
- Mount/eject via udisksctl (www-data with polkit)
- External storage registration via PHP API (GlobalStoragesService)
- Vue.js UI: disk cards, mount/eject/ingest buttons, status badges
- Ingest pipeline framework (ScanStep, DeduplicateStep, ClassifyStep)
- Event system (DiskMounted, DiskUnmounted, IngestCompleted)
- Listener architecture (TriggerScanOnMount, LogOnUnmount, NotifyOnIngestComplete)
- DB migration schema (devnull_disks, devnull_operations, devnull_mounts)
- Bridge abstraction for optional Python daemon

## [0.1.0] — 2026-08-06

### Added
- Project scaffolding
- Basic app structure (info.xml, Application.php, routes)
- DiskService with lsblk integration
- MountService with udisksctl integration
- CommandRunner with whitelist and argument escaping
- Vue.js frontend skeleton
