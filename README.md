# /dev/null

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net/)
[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--34-0082c9.svg)](https://nextcloud.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-2.7-4FC08D.svg)](https://vuejs.org/)

> Nextcloud app for external disk auto-ingest: detect, mount, scan, deduplicate, classify.
>
> *"Where your data goes to live."*

> [!WARNING]
> DevNull is under active development and not yet published on the Nextcloud App Store. Mount and storage registration features are being validated — some operations require manual terminal commands as workaround.

## What is this?

DevNull is a Nextcloud app that transforms your personal server into a central ingest point for external drives. Plug in an HD, click mount, and your server detects, mounts, scans, deduplicates, and classifies — all from the Nextcloud web interface.

Named after `/dev/null` — the Unix black hole where data goes to die. This app does the opposite: it rescues forgotten data from drawer drives and gives it an organized life.

## Current Status

| Component | Status | Notes |
|-----------|--------|-------|
| Disk detection (lsblk) | ✅ Working | Recursive, filters system partitions |
| Web UI (Vue.js) | ✅ Working | List disks, mount/eject buttons, badges |
| Mount via udisks2 | ✅ Working | www-data, polkit rules, --force unmount |
| Eject (unmount) | ✅ Working | udisksctl --force |
| Storage registration | 🔧 In progress | Manual workaround via occ CLI |
| Files scan | 🔧 In progress | Manual via `occ files:scan` |
| Ingest pipeline | 📋 Planned | scan → dedup → classify |
| Daemon (hotplug) | 📋 Planned | Python + pyudev for real-time detection |
| App Store publish | 📋 Planned | After v0.3.0 |

## Features

- [x] Detect USB/external drives connected to the server
- [x] List disks in Nextcloud UI (name, size, filesystem, model, serial)
- [x] One-click mount via udisks2 (no root required)
- [x] One-click eject with --force
- [x] Badge "Montado" for mounted disks
- [x] Warning banner when udisks2 not installed
- [x] Filter system partitions (/, /boot, /mnt/*)
- [ ] Auto-register as Nextcloud external storage on mount
- [ ] Auto files:scan after mount
- [ ] Auto-remove storage on eject
- [ ] Ingest pipeline: scan → deduplicate → classify (AI)
- [ ] Operation history log
- [ ] Optional Python daemon for hotplug detection
- [ ] Nextcloud App Store publication

## Supported Environments

| OS | Nextcloud | PHP | Status |
|----|-----------|-----|--------|
| Debian 12+ | 28-34 | 8.2+ | ✅ Tested |
| Ubuntu 22.04+ | 28-34 | 8.2+ | Should work |

## Architecture

```
DevNull/
├── app/                           # Nextcloud PHP app
│   ├── appinfo/                   # info.xml, routes.php
│   ├── lib/
│   │   ├── AppInfo/               # Bootstrap + DI
│   │   ├── Capability/            # Interfaces (contracts)
│   │   ├── Command/               # SecureCommandRunner (exec wrapper)
│   │   ├── Controller/            # API layer (OCS)
│   │   ├── Detection/             # LsblkDetector, DaemonBridgeDetector
│   │   ├── Event/                 # Domain events
│   │   ├── Ingest/                # Pipeline + Steps
│   │   ├── Listener/              # Event reactions
│   │   ├── Migration/             # DB schema
│   │   ├── Mount/                 # Strategy pattern (udisks, sudo)
│   │   └── Storage/               # NC external storage registration
│   ├── src/                       # Vue.js frontend
│   │   ├── components/            # DiskCard, DiskList, OperationLog
│   │   └── App.vue
│   └── js/                        # Pre-built bundle
├── daemon/                        # Python daemon (optional)
│   ├── devnull_daemon/
│   │   ├── api/                   # FastAPI REST
│   │   ├── detection/             # udev + polling
│   │   └── mount/                 # Strategy pattern
│   └── systemd/                   # Service unit
├── docs/                          # Project plan, architecture
└── scripts/                       # Setup, packaging
```

## Tech Stack

- **Backend:** PHP 8.2+ / Nextcloud App Framework
- **Frontend:** Vue.js 2.7 + @nextcloud/vue + Webpack 5
- **Mount:** udisks2 (userspace, polkit) or sudo mount (fallback)
- **Detection:** lsblk --json (local) or pyudev (daemon)
- **Daemon:** Python 3.11+ / FastAPI (optional enhancer)
- **Deploy:** symlink to NC apps/ + occ app:enable

## Building

### Prerequisites

- [Node.js](https://nodejs.org/) (20+) — for frontend build
- Nextcloud 28+ server — for deployment

### Development

```bash
cd app
npm install --legacy-peer-deps
npm run dev     # watch mode
npm run build   # production
```

### Deploy to server

```bash
cd /opt
git clone https://github.com/luisfacarmo/devnull.git
ln -s /opt/devnull/app /var/www/nextcloud/apps/devnull
sudo -u www-data php /var/www/nextcloud/occ app:enable devnull
```

### System dependencies

```bash
sudo apt install udisks2 util-linux ntfs-3g exfatprogs hfsprogs
bash scripts/setup-permissions.sh
```

## Contributing

Contributions are welcome! Areas where help is needed:

1. **Storage registration** — making `files_external:create` work from web context
2. **New mount strategies** — Docker volumes, NFS, CIFS
3. **Device support** — testing with different USB enclosures
4. **Platform support** — macOS, other Linux distros

## Credits

- Inspired by the need to organize 10+ years of external drives
- Built with [Nextcloud App Framework](https://docs.nextcloud.com/server/stable/developer_manual/)
- UI components from [@nextcloud/vue](https://github.com/nextcloud-libraries/nextcloud-vue)

## License

[AGPL-3.0](LICENSE)
