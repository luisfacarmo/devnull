# /dev/null

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net/)
[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--34-0082c9.svg)](https://nextcloud.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-2.7-4FC08D.svg)](https://vuejs.org/)

> Nextcloud app for external disk auto-ingest: detect, mount, scan, deduplicate, classify.
>
> *"Where your data goes to live."*
>
> — Named after `/dev/null`, the Unix black hole where data goes to die. This app does the opposite.

> [!WARNING]
> DevNull is under active development (v0.3.0). Core features are functional but awaiting server validation. Not yet published on the Nextcloud App Store (certificate pending: [PR #1152](https://github.com/nextcloud/app-certificate-requests/pull/1152)).

## What is this?

DevNull transforms your Nextcloud server into a central ingest point for external drives. Plug in a disk, click mount, and your server detects, mounts, scans, deduplicates, and classifies — all from the web interface. No terminal required.

Built for the person with 10+ external drives in a drawer, full of photos and documents that deserve an organized life.

## Status

| Component | Status | Notes |
|-----------|--------|-------|
| Disk detection (lsblk) | 🟢 Working | Recursive, filters system partitions |
| Web UI (Vue.js) | 🟢 Working | Disk cards, mount/eject/process buttons, badges |
| Mount via udisks2 | 🟢 Working | www-data with polkit, --force unmount |
| Eject (unmount) | 🟢 Working | Removes NC external storage automatically |
| Storage registration | 🟢 Working | PHP API (GlobalStoragesService), no subprocess |
| File scan (auto) | 🟢 Working | \OC\Files\Utils\Scanner via PHP API |
| Ingest pipeline | 🟢 Working | Scan (sync) + Dedup/Classify (background jobs) |
| Permissions | 🟢 Working | Admin mounts; users see shared storage |
| Error handling | 🟢 Working | Consistent {success, error, code} responses |
| DB schema | 🟡 Pending deploy | Migration exists, needs disable/enable cycle |
| Daemon (hotplug) | 📋 Planned | Python + pyudev for real-time detection |
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
- [x] Filter system partitions (/, /boot, swap)
- [x] `.devnull` marker file on mounted disks
- [x] Badge "Montado" for mounted disks
- [x] Warning banner when udisks2 not available
- [x] Structured error responses with error codes
- [x] PHPStan level 5 compliance
- [ ] Operation history log (DB tables pending migration)
- [ ] Optional Python daemon for hotplug detection
- [ ] Auto-mount rules (mount on plug)
- [ ] Nextcloud notifications on ingest completion
- [ ] Nextcloud App Store publication

## Architecture

```
DevNull/
├── app/                           # Nextcloud PHP app
│   ├── appinfo/                   # info.xml, routes.php
│   ├── lib/
│   │   ├── AppInfo/               # Bootstrap + DI (IBootstrap)
│   │   ├── Capability/            # Interfaces (DiskDetector, MountStrategy, StorageRegistrar)
│   │   ├── Command/               # SecureCommandRunner (whitelisted exec)
│   │   ├── Controller/            # OCS API (Disk, Mount, Ingest, Status, Operation)
│   │   ├── Detection/             # LsblkDetector, DetectorFactory
│   │   ├── Event/                 # DiskMounted, DiskUnmounted, IngestCompleted
│   │   ├── Ingest/                # Pipeline + Steps (Scan, Deduplicate, Classify)
│   │   ├── Listener/              # TriggerScanOnMount, LogOnUnmount, NotifyOnIngestComplete
│   │   ├── Migration/             # DB schema (disks, operations, mounts)
│   │   ├── Mount/                 # UdisksMountStrategy, SudoMountStrategy, NullMountStrategy
│   │   ├── Bridge/                # HttpDaemonClient, NullDaemonClient
│   │   └── Storage/               # NextcloudStorageRegistrar (PHP API)
│   ├── src/                       # Vue.js frontend
│   │   ├── components/            # DiskCard, DiskList, OperationLog
│   │   └── App.vue
│   └── js/                        # Built frontend bundle
├── daemon/                        # Python daemon (optional, planned)
├── docs/                          # Audit reports, continuation prompts
└── scripts/                       # Setup helpers
```

## Tech Stack

- **Backend:** PHP 8.2+ / Nextcloud App Framework 28+
- **Frontend:** Vue.js 2.7 / @nextcloud/vue / Webpack 5
- **Mount:** udisks2 (userspace, polkit) or sudo mount (fallback)
- **Detection:** lsblk --json --output --recursive
- **Storage:** files_external GlobalStoragesService (PHP API)
- **Scan:** \OC\Files\Utils\Scanner (same as `occ files:scan`)
- **Jobs:** IJobList for dedup/classify scheduling
- **Daemon (planned):** Python 3.11+ / FastAPI / pyudev

## Building

### Prerequisites

- [Node.js](https://nodejs.org/) 20+ (frontend build)
- [Composer](https://getcomposer.org/) (PHP dev deps)
- Nextcloud 28+ server (deployment target)

### Frontend

```bash
cd app
npm install --legacy-peer-deps
npm run build       # production
npm run dev         # watch mode
```

### PHP (dev tools)

```bash
cd app
composer install
composer stan       # PHPStan level 5
composer test       # PHPUnit (when tests exist)
```

### Deploy to server

```bash
# On the server (e.g., LibraryOfAlexandria)
cd /opt/devnull && git pull
sudo -u www-data php /var/www/nextcloud/occ app:disable devnull
sudo -u www-data php /var/www/nextcloud/occ app:enable devnull
sudo systemctl restart apache2
```

### System dependencies

```bash
sudo apt install udisks2 util-linux ntfs-3g exfatprogs hfsprogs
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

## Contributing

Contributions welcome. Areas where help is needed:

1. **Testing** — PHPUnit tests for controllers and services
2. **New mount strategies** — Docker volumes, NFS, CIFS
3. **Device support** — testing with different USB enclosures
4. **Platform support** — Ubuntu, Fedora, Arch variations
5. **Daemon** — Python hotplug detection implementation

## License

[AGPL-3.0](LICENSE)
