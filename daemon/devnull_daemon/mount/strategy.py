"""Mount strategy interface and factory."""

import json
import logging
import re
import subprocess
from abc import ABC, abstractmethod

from devnull_daemon.config import settings
from devnull_daemon.models.disk import MountOperationResult

logger = logging.getLogger(__name__)

DEVICE_PATTERN = re.compile(r"^[a-z]+[0-9]*$")


class MountStrategy(ABC):
    """Base class for mount strategies."""

    @abstractmethod
    def mount(self, device: str) -> MountOperationResult:
        ...

    @abstractmethod
    def unmount(self, device: str) -> MountOperationResult:
        ...

    def _validate_device(self, device: str) -> bool:
        return bool(DEVICE_PATTERN.match(device))

    def _detect_mountpoint(self, device: str) -> str | None:
        """Detect current mountpoint via lsblk."""
        try:
            result = subprocess.run(
                ["lsblk", "-n", "-o", "MOUNTPOINTS", f"/dev/{device}"],
                capture_output=True,
                text=True,
                timeout=5,
            )
            mp = result.stdout.strip()
            return mp if mp else None
        except (subprocess.TimeoutExpired, FileNotFoundError):
            return None


class UdisksMountStrategy(MountStrategy):
    """Mount using udisksctl (userspace, no root)."""

    def mount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

        # Check if already mounted before calling udisksctl
        existing = self._detect_mountpoint(device)
        if existing:
            logger.info("Device %s already mounted at %s", device, existing)
            return MountOperationResult(success=True, mountpoint=existing)

        device_path = f"/dev/{device}"
        try:
            result = subprocess.run(
                ["udisksctl", "mount", "-b", device_path, "--no-user-interaction"],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0:
                # Parse actual mountpoint from stdout
                # Format: "Mounted /dev/sdb1 at /media/www-data/LABEL."
                mountpoint = self._parse_mountpoint(result.stdout, device)
                return MountOperationResult(success=True, mountpoint=mountpoint)

            # Check if error is AlreadyMounted
            stderr = result.stderr.strip()
            if "AlreadyMounted" in stderr or "already mounted" in stderr:
                detected = self._detect_mountpoint(device)
                if detected:
                    return MountOperationResult(success=True, mountpoint=detected)

            return MountOperationResult(success=False, error=stderr or f"exit {result.returncode}")

        except subprocess.TimeoutExpired:
            return MountOperationResult(success=False, error="Mount timed out (30s)")
        except FileNotFoundError:
            return MountOperationResult(success=False, error="udisksctl not found")

    def unmount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

        device_path = f"/dev/{device}"
        try:
            result = subprocess.run(
                ["udisksctl", "unmount", "-b", device_path, "--no-user-interaction", "--force"],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0:
                return MountOperationResult(success=True)
            return MountOperationResult(success=False, error=result.stderr.strip())
        except subprocess.TimeoutExpired:
            return MountOperationResult(success=False, error="Unmount timed out (30s)")
        except FileNotFoundError:
            return MountOperationResult(success=False, error="udisksctl not found")

    def _parse_mountpoint(self, stdout: str, device: str) -> str:
        """Parse mountpoint from udisksctl output.

        Example: "Mounted /dev/sdb1 at /media/www-data/BÁRBARA."
        """
        match = re.search(r"at\s+(.+?)\.?\s*$", stdout.strip())
        if match:
            return match.group(1).strip()

        # Fallback: detect via lsblk
        detected = self._detect_mountpoint(device)
        if detected:
            return detected

        # Last resort: constructed path
        return f"{settings.mount_base}/{device}"


class SudoMountStrategy(MountStrategy):
    """Mount using sudo mount (requires sudoers rule)."""

    def mount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

        # Check if already mounted
        existing = self._detect_mountpoint(device)
        if existing:
            return MountOperationResult(success=True, mountpoint=existing)

        device_path = f"/dev/{device}"
        mountpoint = f"{settings.mount_base}/{device}"

        try:
            subprocess.run(["sudo", "mkdir", "-p", mountpoint], check=True, timeout=5)
            result = subprocess.run(
                ["sudo", "mount", device_path, mountpoint],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0:
                return MountOperationResult(success=True, mountpoint=mountpoint)
            return MountOperationResult(success=False, error=result.stderr.strip())
        except subprocess.TimeoutExpired:
            return MountOperationResult(success=False, error="Mount timed out")
        except (FileNotFoundError, subprocess.CalledProcessError) as e:
            return MountOperationResult(success=False, error=str(e))

    def unmount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

        device_path = f"/dev/{device}"
        try:
            result = subprocess.run(
                ["sudo", "umount", device_path],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0:
                return MountOperationResult(success=True)
            return MountOperationResult(success=False, error=result.stderr.strip())
        except subprocess.TimeoutExpired:
            return MountOperationResult(success=False, error="Unmount timed out")
        except FileNotFoundError as e:
            return MountOperationResult(success=False, error=str(e))


def get_mount_strategy() -> MountStrategy:
    """Factory: returns the configured mount strategy."""
    if settings.mount_strategy == "udisks":
        return UdisksMountStrategy()
    elif settings.mount_strategy == "sudo":
        return SudoMountStrategy()
    else:
        raise ValueError(f"Unknown mount strategy: {settings.mount_strategy}")
