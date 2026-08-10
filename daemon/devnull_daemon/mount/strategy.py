"""Mount strategy interface and factory."""

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


class UdisksMountStrategy(MountStrategy):
    """Mount using udisksctl (userspace, no root)."""

    def mount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

        device_path = f"/dev/{device}"
        try:
            result = subprocess.run(
                ["udisksctl", "mount", "-b", device_path, "--no-user-interaction"],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0:
                # Parse mountpoint from output
                mountpoint = f"{settings.mount_base}/{device}"
                return MountOperationResult(success=True, mountpoint=mountpoint)
            return MountOperationResult(success=False, error=result.stderr.strip())
        except (subprocess.TimeoutExpired, FileNotFoundError) as e:
            return MountOperationResult(success=False, error=str(e))

    def unmount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

        device_path = f"/dev/{device}"
        try:
            result = subprocess.run(
                ["udisksctl", "unmount", "-b", device_path, "--no-user-interaction"],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0:
                return MountOperationResult(success=True)
            return MountOperationResult(success=False, error=result.stderr.strip())
        except (subprocess.TimeoutExpired, FileNotFoundError) as e:
            return MountOperationResult(success=False, error=str(e))


class SudoMountStrategy(MountStrategy):
    """Mount using sudo mount (requires sudoers rule)."""

    def mount(self, device: str) -> MountOperationResult:
        if not self._validate_device(device):
            return MountOperationResult(success=False, error="Invalid device name")

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
        except (subprocess.TimeoutExpired, FileNotFoundError, subprocess.CalledProcessError) as e:
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
        except (subprocess.TimeoutExpired, FileNotFoundError) as e:
            return MountOperationResult(success=False, error=str(e))


def get_mount_strategy() -> MountStrategy:
    """Factory: returns the configured mount strategy."""
    if settings.mount_strategy == "udisks":
        return UdisksMountStrategy()
    elif settings.mount_strategy == "sudo":
        return SudoMountStrategy()
    else:
        raise ValueError(f"Unknown mount strategy: {settings.mount_strategy}")
