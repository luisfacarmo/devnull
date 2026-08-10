"""FastAPI application — REST API for the DevNull daemon."""

from fastapi import FastAPI, Depends, HTTPException, Header
from typing import Annotated

from devnull_daemon.config import settings
from devnull_daemon.detection.poller import list_available_disks
from devnull_daemon.mount.strategy import get_mount_strategy
from devnull_daemon.models.disk import DiskInfo, MountRequest, MountResponse


def verify_token(x_devnull_token: Annotated[str | None, Header()] = None) -> None:
    """Simple token auth for app<->daemon communication."""
    if x_devnull_token != settings.auth_token:
        raise HTTPException(status_code=401, detail="Invalid token")


def create_app() -> FastAPI:
    """Create and configure the FastAPI application."""

    app = FastAPI(
        title="DevNull Daemon",
        version="0.1.0",
        description="Hardware detection and mount engine for DevNull",
    )

    @app.get("/api/v1/health")
    async def health():
        return {
            "status": "healthy",
            "version": "0.1.0",
            "detection_mode": settings.detection_mode,
            "mount_strategy": settings.mount_strategy,
        }

    @app.get("/api/v1/disks", dependencies=[Depends(verify_token)])
    async def list_disks() -> list[DiskInfo]:
        """List available (unmounted) block devices."""
        return list_available_disks()

    @app.post("/api/v1/mount", dependencies=[Depends(verify_token)])
    async def mount_disk(request: MountRequest) -> MountResponse:
        """Mount a device."""
        strategy = get_mount_strategy()
        result = strategy.mount(request.device)
        if result.success:
            return MountResponse(success=True, mountpoint=result.mountpoint)
        return MountResponse(success=False, error=result.error)

    @app.post("/api/v1/unmount", dependencies=[Depends(verify_token)])
    async def unmount_disk(request: MountRequest) -> MountResponse:
        """Unmount a device."""
        strategy = get_mount_strategy()
        result = strategy.unmount(request.device)
        if result.success:
            return MountResponse(success=True)
        return MountResponse(success=False, error=result.error)

    return app
