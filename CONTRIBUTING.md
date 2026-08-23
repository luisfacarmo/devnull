# Contributing

Contributions are welcome! Here's how to get started.

## How to contribute

1. Fork this repository
2. Create a branch for your change (`git checkout -b feat/my-feature`)
3. Make your changes
4. Test locally (see below)
5. Commit with a clear message (`feat: add X` or `fix: resolve Y`)
6. Open a Pull Request

## Local setup

### Requirements
- PHP 8.2+
- Python 3.10+ (for the hotplug daemon)
- Nextcloud 34 development environment
- Composer 2.x
- `pyudev` and `dbus-python` (daemon dependencies)

### Install
```bash
cd app
composer install

# Daemon dependencies
pip install pyudev dbus-python
```

### Run checks
```bash
# Static analysis
./vendor/bin/phpstan analyse --level 5
```

### Test in Nextcloud
```bash
# Symlink the app
ln -s /path/to/devnull/app /var/www/nextcloud/apps/devnull

# Enable it
sudo -u www-data php occ app:enable devnull

# Run the daemon (needs root for udev)
sudo python3 daemon/devnull_daemon.py
```

## Commit style

We follow [Conventional Commits](https://www.conventionalcommits.org/):
- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — documentation only
- `chore:` — maintenance, deps, CI

## What we accept

- Bug fixes with evidence (logs, screenshots, steps to reproduce)
- New features discussed in an Issue first
- Documentation improvements
- Support for additional filesystem types or device classes

## What we don't accept

- Breaking changes without prior discussion
- Code without testing instructions
- PRs that mix unrelated changes
- Direct commits to `master` — always use a PR

## Architecture notes

- **NC App** (PHP): mount management, scan jobs, deduplication, dashboard widget
- **Daemon** (Python): pyudev hotplug detection, dbus bridge to NC app
- **Storage**: Nextcloud user filesystem (not AppData)
- **Dedup**: SHA-256 fingerprinting before import

## Questions?

Open an Issue. We'll respond as soon as possible.
