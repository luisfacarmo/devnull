"""Disk detection via lsblk (polling fallback)."""

import json
import logging
import subprocess

from devnull_daemon.models.disk import DiskInfo

logger = logging.getLogger(__name__)


def list_available_disks() -> list[DiskInfo]:
    """List unmounted partitions using lsblk --json."""
    try:
        result = subprocess.run(
            [
                "lsblk",
                "--json",
                "--output",
                "NAME,SIZE,FSTYPE,LABEL,MOUNTPOINT,TYPE,SERIAL,MODEL",
            ],
            capture_output=True,
            text=True,
            timeout=10,
        )

        if result.returncode != 0:
            logger.error("lsblk failed: %s", result.stderr)
            return []

        data = json.loads(result.stdout)
        devices = data.get("blockdevices", [])

        disks: list[DiskInfo] = []
        for dev in devices:
            # Only partitions, unmounted, with a known filesystem
            if dev.get("type") != "part":
                continue
            if dev.get("mountpoint"):
                continue
            if not dev.get("fstype"):
                continue

            disks.append(
                DiskInfo(
                    name=dev["name"],
                    size=dev.get("size", "unknown"),
                    fstype=dev.get("fstype"),
                    label=dev.get("label"),
                    mountpoint=dev.get("mountpoint"),
                    serial=dev.get("serial"),
                    model=dev.get("model"),
                )
            )

        return disks

    except (subprocess.TimeoutExpired, json.JSONDecodeError, FileNotFoundError) as e:
        logger.error("Disk detection failed: %s", e)
        return []
