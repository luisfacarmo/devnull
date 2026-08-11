"""Daemon entrypoint — starts FastAPI server + UdevMonitor in background."""

import logging
import threading

import uvicorn

from devnull_daemon.config import settings
from devnull_daemon.api.server import create_app
from devnull_daemon.events import event_bus, DiskEvent
from devnull_daemon.notifications.webhook import NextcloudWebhookNotifier, EventType

logger = logging.getLogger(__name__)


def _start_udev_monitor(notifier: NextcloudWebhookNotifier) -> None:
    """Start the udev monitor in a background thread."""
    from devnull_daemon.detection.udev_monitor import UdevMonitor
    from devnull_daemon.mount.strategy import get_mount_strategy

    monitor = UdevMonitor()
    if not monitor.is_available:
        logger.warning("Udev monitor not available — hotplug detection disabled")
        return

    def on_disk_added(disk_info: dict) -> None:
        """Handle new disk plugged in."""
        logger.info("Hotplug: disk added — %s", disk_info.get("name"))

        # Auto-mount if configured
        if settings.auto_mount_on_plug:
            device = disk_info.get("name")
            if device:
                logger.info("Auto-mounting %s", device)
                strategy = get_mount_strategy()
                result = strategy.mount(device)
                if result.success:
                    disk_info["mountpoint"] = result.mountpoint
                    logger.info("Auto-mount success: %s → %s", device, result.mountpoint)
                else:
                    logger.warning("Auto-mount failed: %s — %s", device, result.error)

        # Publish to SSE event bus
        event_bus.publish(DiskEvent(event_type="disk_added", data=disk_info))

        # Notify Nextcloud
        notifier.notify_disk_added(disk_info)

    def on_disk_removed(disk_info: dict) -> None:
        """Handle disk unplugged."""
        logger.info("Hotplug: disk removed — %s", disk_info.get("name"))

        # Publish to SSE event bus
        event_bus.publish(DiskEvent(event_type="disk_removed", data=disk_info))

        # Notify Nextcloud
        notifier.notify_disk_removed(disk_info)

    monitor.start(on_add=on_disk_added, on_remove=on_disk_removed)
    logger.info("Udev monitor running in background thread")


def _start_poller(notifier: NextcloudWebhookNotifier) -> None:
    """Start polling-based detection in a background thread."""
    import time
    from devnull_daemon.detection.poller import list_available_disks

    logger.info("Polling mode: checking every %ds", settings.poll_interval)

    known_disks: set[str] = set()

    # Initial snapshot
    for disk in list_available_disks():
        known_disks.add(disk.name)

    while True:
        time.sleep(settings.poll_interval)
        try:
            current = {d.name: d for d in list_available_disks()}
            current_names = set(current.keys())

            # New disks
            for name in current_names - known_disks:
                disk = current[name]
                disk_info = disk.model_dump()
                logger.info("Poller: new disk detected — %s", name)
                notifier.notify_disk_added(disk_info)

            # Removed disks
            for name in known_disks - current_names:
                logger.info("Poller: disk removed — %s", name)
                notifier.notify_disk_removed({"name": name})

            known_disks = current_names
        except Exception as e:
            logger.error("Poller error: %s", e)


def run() -> None:
    """Start the daemon server with hotplug detection."""
    logging.basicConfig(
        level=getattr(logging, settings.log_level.upper(), logging.INFO),
        format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    )

    logger.info("DevNull Daemon starting (detection=%s, mount=%s)",
                settings.detection_mode, settings.mount_strategy)

    # Create webhook notifier
    notifier = NextcloudWebhookNotifier()

    # Start detection in background thread
    if settings.detection_mode == "udev":
        thread = threading.Thread(target=_start_udev_monitor, args=(notifier,), daemon=True)
    else:
        thread = threading.Thread(target=_start_poller, args=(notifier,), daemon=True)

    thread.start()

    # Start FastAPI server (blocking)
    app = create_app()
    uvicorn.run(
        app,
        host=settings.host,
        port=settings.port,
        log_level=settings.log_level.lower(),
    )


if __name__ == "__main__":
    run()
