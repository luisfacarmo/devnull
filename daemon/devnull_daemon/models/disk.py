"""Data models for the daemon API."""

from dataclasses import dataclass
from pydantic import BaseModel


class DiskInfo(BaseModel):
    """Block device information returned by detection."""

    name: str
    size: str
    fstype: str | None = None
    label: str | None = None
    mountpoint: str | None = None
    serial: str | None = None
    model: str | None = None


class MountRequest(BaseModel):
    """Request to mount/unmount a device."""

    device: str


class MountResponse(BaseModel):
    """Result of a mount/unmount operation."""

    success: bool
    mountpoint: str | None = None
    error: str | None = None


@dataclass
class MountOperationResult:
    """Internal result of a mount strategy operation."""

    success: bool
    mountpoint: str | None = None
    error: str | None = None
