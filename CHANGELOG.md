# Changelog

All notable changes to DevNull will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
