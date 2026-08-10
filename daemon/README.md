# DevNull Daemon

> Hardware detection and mount engine for the DevNull Nextcloud app.

## What it does

- Detects USB/external drives via udev (real-time) or polling (fallback)
- Mounts/unmounts via udisksctl or sudo mount
- Exposes REST API for the Nextcloud PHP app to consume
- Runs as systemd service

## Requirements

- Python 3.11+
- Linux (Debian 12+ / Ubuntu 22.04+)
- udisks2 or sudo mount access
- pyudev (for real-time detection)

## Installation

```bash
# Clone and setup
cd /opt
git clone https://github.com/luiscarmo/devnull.git
cd devnull/daemon

# Create venv and install
python3 -m venv .venv
source .venv/bin/activate
pip install -e .

# Configure
cp .env.example .env
# Edit .env — set DEVNULL_AUTH_TOKEN to a secure value

# Install systemd service
sudo cp systemd/devnull-daemon.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now devnull-daemon
```

## API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/health` | Health check (no auth) |
| GET | `/api/v1/disks` | List available disks |
| POST | `/api/v1/mount` | Mount a device |
| POST | `/api/v1/unmount` | Unmount a device |

All endpoints (except health) require header: `X-DevNull-Token: <token>`

## Development

```bash
pip install -e ".[dev]"
pytest
```

## License

AGPL-3.0
