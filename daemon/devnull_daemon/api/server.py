"""FastAPI application — REST API + SSE for the DevNull daemon."""

import asyncio
import json
import logging

from fastapi import FastAPI, Depends, HTTPException, Header, Request
from fastapi.responses import StreamingResponse
from typing import Annotated

from devnull_daemon.config import settings
from devnull_daemon.detection.poller import list_available_disks
from devnull_daemon.events import event_bus, DiskEvent
from devnull_daemon.mount.strategy import get_mount_strategy
from devnull_daemon.models.disk import DiskInfo, MountRequest, MountResponse

logger = logging.getLogger(__name__)


def verify_token(x_devnull_token: Annotated[str | None, Header()] = None) -> None:
    """Simple token auth for app<->daemon communication."""
    if x_devnull_token != settings.auth_token:
        raise HTTPException(status_code=401, detail="Invalid token")


def create_app() -> FastAPI:
    """Create and configure the FastAPI application."""

    app = FastAPI(
        title="DevNull Daemon",
        version="0.4.0",
        description="Hardware detection, mount engine, and real-time events for DevNull",
    )

    @app.on_event("startup")
    async def on_startup():
        """Register the event loop with the event bus."""
        loop = asyncio.get_running_loop()
        event_bus.set_loop(loop)
        logger.info("Event bus connected to asyncio loop")

    @app.get("/api/v1/health")
    async def health():
        return {
            "status": "healthy",
            "version": "0.4.0",
            "detection_mode": settings.detection_mode,
            "mount_strategy": settings.mount_strategy,
            "auto_mount": settings.auto_mount_on_plug,
        }

    @app.get("/api/v1/disks", dependencies=[Depends(verify_token)])
    async def get_disks() -> list[DiskInfo]:
        """List available (unmounted) block devices."""
        return list_available_disks()

    @app.post("/api/v1/mount", dependencies=[Depends(verify_token)])
    async def mount_disk(request: MountRequest) -> MountResponse:
        """Mount a device."""
        strategy = get_mount_strategy()
        result = strategy.mount(request.device)
        if result.success:
            event_bus.publish(DiskEvent(
                event_type="mount_complete",
                data={"device": request.device, "mountpoint": result.mountpoint},
            ))
            return MountResponse(success=True, mountpoint=result.mountpoint)
        return MountResponse(success=False, error=result.error)

    @app.post("/api/v1/unmount", dependencies=[Depends(verify_token)])
    async def unmount_disk(request: MountRequest) -> MountResponse:
        """Unmount a device."""
        strategy = get_mount_strategy()
        result = strategy.unmount(request.device)
        if result.success:
            event_bus.publish(DiskEvent(
                event_type="unmount_complete",
                data={"device": request.device},
            ))
            return MountResponse(success=True)
        return MountResponse(success=False, error=result.error)

    @app.get("/api/v1/events")
    async def events_stream(request: Request, last_event_id: float = 0.0):
        """Server-Sent Events stream for real-time disk notifications.

        Frontend connects to this endpoint to receive live updates
        when disks are plugged/unplugged/mounted/unmounted.

        Query param `last_event_id` replays missed events (reconnection support).
        """

        async def generate():
            try:
                async for event in event_bus.subscribe(last_event_id):
                    if await request.is_disconnected():
                        break
                    data = json.dumps({
                        "type": event.event_type,
                        "data": event.data,
                        "timestamp": event.timestamp,
                    })
                    yield f"id: {event.timestamp}\nevent: {event.event_type}\ndata: {data}\n\n"
            except asyncio.CancelledError:
                pass

        return StreamingResponse(
            generate(),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache",
                "Connection": "keep-alive",
                "X-Accel-Buffering": "no",
            },
        )

    @app.get("/api/v1/events/history", dependencies=[Depends(verify_token)])
    async def events_history():
        """Get recent event history (last 50 events)."""
        return [
            {
                "type": e.event_type,
                "data": e.data,
                "timestamp": e.timestamp,
            }
            for e in event_bus.history
        ]

    return app
