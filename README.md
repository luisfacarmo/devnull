# /dev/null

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net/)
[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--34-0082c9.svg)](https://nextcloud.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-2.7-4FC08D.svg)](https://vuejs.org/)
[![Python](https://img.shields.io/badge/Python-3.11+-3776AB.svg)](https://python.org/)

> Nextcloud app for external disk auto-ingest: detect, mount, scan, deduplicate, classify.
>
> *"Where your data goes to live."*
>
> — Named after `/dev/null`, the Unix black hole where data goes to die. This app does the opposite.

> [!WARNING]
> DevNull is feature-complete (v0.5.0) but not yet published on the Nextcloud App Store (certificate pending: [PR #1152](https://github.com/nextcloud/app-certificate-requests/pull/1152)).

## What is this?

DevNull transforms your Nextcloud server into a central ingest point for external drives. Plug in a disk, click mount, and your server detects, mounts, scans, deduplicates, and classifies — all from the web interface. No terminal required.

Built for the person with 10+ external drives in a drawer, full of photos and documents that deserve an organized life.

## Status

| Component | Status | Notes |
|-----------|--------|-------|
| Disk detection (lsblk) | 🟢 Working | Recursive, filters system partitions |
| Web UI (Vue.js) | 🟢 Working | Disk cards, mount/eject/process buttons, badges |
| Mount via udisks2 | 🟢 Working | www-data with polkit, handles AlreadyMounted |
| Eject (unmount) | 🟢 Working | Removes NC external storage by saved ID |
| Storage registration | 🟢 Working | PHP API (GlobalStoragesService), no subprocess |
| File scan (auto) | 🟢 Working | \OC\Files\Utils\Scanner (NC 34 compatible) |
| Ingest pipeline | 🟢 Working | Scan (sync) + Dedup/Classify (background jobs) |
| Permissions | 🟢 Working | Admin mounts; users see shared storage |
| Error handling | 🟢 Working | Consistent {success, error, code} + HTTP status |
| Notifications | 🟢 Working | NC notification panel (mount, ingest, hotplug) |
| Auto-classify | 🟢 Working | Triggers Recognize after scan (configurable) |
| Dashboard widget | 🟢 Working | IAPIWidgetV2 on NC home screen |
| Admin settings | 🟢 Working | Automation rules in NC admin panel |
| Daemon (hotplug) | 🟢 Working | Python + pyudev, SSE, webhook bridge |
| DB schema | 🟢 Working | 3 tables (disks, operations, mounts) |
| App Store | 📋 Pending | Certificate PR open |

## Features

- [x] Detect USB/external drives connected to the server
- [x] List disks in Nextcloud UI (name, size, filesystem, model, serial)
- [x] One-click mount via udisks2 (no root required)
- [x] One-click eject with --force
- [x] Auto-register as Nextcloud external storage on mount
- [x] Auto file scan after mount (content visible immediately)
- [x] Auto-remove external storage on eject (clean lifecycle)
- [x] Ingest pipeline: scan → deduplicate → classify (AI)
- [x] Background job scheduling for heavy operations (dedup, classify)
- [x] Auto-classify via Recognize after scan (configurable)
- [x] Nextcloud notifications (mount complete, ingest complete, disk detected)
- [x] Dashboard widget (mounted disks + recent ops on NC home)
- [x] Admin settings page (automation rules, daemon config)
- [x] Python daemon with hotplug detection (pyudev + polling fallback)
- [x] SSE real-time events endpoint
- [x] Webhook bridge (daemon → NC app)
- [x] Auto-mount on plug (configurable)
- [x] Deploy script (`scripts/deploy.sh`)
- [x] Automated test suite (`scripts/test.sh`)
- [x] Filter system partitions (/, /boot, swap)
- [x] `.devnull` marker file on mounted disks
- [x] PHPStan level 5 compliance
- [x] Structured error responses with error codes
- [ ] Nextcloud App Store publication (awaiting certificate)

## Architecture

```
DevNull/
├── app/                           # Nextcloud PHP app
│   ├── appinfo/                   # info.xml, routes.php
│   ├── lib/
│   │   ├── AppInfo/               # Bootstrap + DI (IBootstrap)
│   │   ├── Capability/            # Interfaces (DiskDetector, MountStrategy, StorageRegistrar)
│   │   ├── Command/               # SecureCommandRunner (whitelisted exec)
│   │   ├── Controller/            # OCS API (Disk, Mount, Ingest, Status, Operation, Daemon, Settings)
│   │   ├── Dashboard/             # NC Dashboard widget (IAPIWidgetV2)
│   │   ├── Detection/             # LsblkDetector, DetectorFactory
│   │   ├── Event/                 # DiskMounted, DiskUnmounted, IngestCompleted
│   │   ├── Ingest/                # Pipeline + Steps (Scan, Deduplicate, Classify)
│   │   ├── Listener/              # TriggerScanOnMount, LogOnUnmount, NotifyOnIngestComplete
│   │   ├── Migration/             # DB schema (disks, operations, mounts)
│   │   ├── Mount/                 # UdisksMountStrategy, SudoMountStrategy, NullMountStrategy
│   │   ├── Notification/          # Notifier + NotificationService
│   │   ├── Bridge/                # HttpDaemonClient, NullDaemonClient
│   │   ├── Settings/              # AdminSection, AdminSettings
│   │   └── Storage/               # NextcloudStorageRegistrar (PHP API + auto-classify)
│   ├── src/                       # Vue.js frontend
│   │   ├── components/            # DiskCard, DiskList, OperationLog (dashboard)
│   │   └── App.vue
│   ├── templates/                 # Admin settings page
│   └── js/                        # Built frontend bundle
├── daemon/                        # Python daemon (optional enhancer)
│   ├── devnull_daemon/
│   │   ├── api/                   # FastAPI REST + SSE
│   │   ├── detection/             # UdevMonitor + polling fallback
│   │   ├── mount/                 # Strategy pattern (udisks, sudo)
│   │   ├── notifications/         # Webhook notifier → NC app
│   │   ├── events.py              # EventBus (async pub/sub)
│   │   └── models/                # Pydantic models
│   └── systemd/                   # Service unit
├── scripts/
│   ├── deploy.sh                  # One-command deploy
│   └── test.sh                    # Automated test suite
└── docs/                          # Audit reports, continuation prompts
```

## Tech Stack

- **Backend:** PHP 8.2+ / Nextcloud App Framework 28+
- **Frontend:** Vue.js 2.7 / @nextcloud/vue / Webpack 5
- **Daemon:** Python 3.11+ / FastAPI / pyudev / httpx
- **Mount:** udisks2 (userspace, polkit) or sudo mount (fallback)
- **Detection:** lsblk --json (PHP) / pyudev real-time (daemon)
- **Storage:** files_external GlobalStoragesService (PHP API)
- **Scan:** \OC\Files\Utils\Scanner (NC 34, IUser + SetupManager)
- **Jobs:** IJobList for dedup/classify/Recognize scheduling
- **Notifications:** OCP\Notification\IManager
- **Dashboard:** IAPIWidgetV2 + IReloadableWidget

## Quick Deploy

```bash
# On the server
cd /opt/devnull && git pull && sudo bash scripts/deploy.sh

# With daemon (hotplug detection)
cd /opt/devnull && git pull && sudo bash scripts/deploy.sh --with-daemon

# Run tests
export DEVNULL_TEST_PASS='your-nc-password'
bash /opt/devnull/scripts/test.sh
```

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/disks` | User | List detected disks |
| POST | `/api/v1/mount` | Admin | Mount a device |
| POST | `/api/v1/unmount` | Admin | Eject a device |
| POST | `/api/v1/ingest` | Admin | Start ingest pipeline |
| GET | `/api/v1/ingest/steps` | User | List available steps |
| GET | `/api/v1/status` | User | Current mount/operation status |
| GET | `/api/v1/logs` | User | Operation history |
| GET | `/api/v1/settings` | Admin | Get automation settings |
| PUT | `/api/v1/settings` | Admin | Update automation settings |
| POST | `/api/v1/daemon/event` | Token | Daemon webhook receiver |
| GET | `/api/v1/daemon/config` | Token | Daemon self-configuration |

### Daemon API (port 9876)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/health` | — | Health check |
| GET | `/api/v1/disks` | Token | List available disks |
| POST | `/api/v1/mount` | Token | Mount a device |
| POST | `/api/v1/unmount` | Token | Unmount a device |
| GET | `/api/v1/events` | — | SSE real-time stream |
| GET | `/api/v1/events/history` | Token | Recent event history |

## Contributing

Contributions welcome. Areas where help is needed:

1. **Testing** — PHPUnit tests for controllers and services
2. **New mount strategies** — Docker volumes, NFS, CIFS
3. **Device support** — testing with different USB enclosures
4. **Platform support** — Ubuntu, Fedora, Arch variations
5. **Frontend** — Vue.js admin settings component

## License

[AGPL-3.0](LICENSE)
