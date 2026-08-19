"""Real-time disk detection via pyudev (Linux udev subsystem)."""

import logging
from typing import Callable

logger = logging.getLogger(__name__)


class UdevMonitor:
    """Monitors udev events for block device changes.

    Usage:
        monitor = UdevMonitor()
        monitor.start(on_add=handle_new_disk, on_remove=handle_removed_disk)
    """

    def __init__(self) -> None:
        try:
            import pyudev

            self._context = pyudev.Context()
            self._monitor = pyudev.Monitor.from_netlink(self._context)
            self._monitor.filter_by(subsystem="block", device_type="partition")
            self._available = True
        except (ImportError, OSError):
            self._available = False
            logger.warning("pyudev not available — udev monitoring disabled")

    @property
    def is_available(self) -> bool:
        return self._available

    def start(
        self,
        on_add: Callable[[dict], None] | None = None,
        on_remove: Callable[[dict], None] | None = None,
    ) -> None:
        """Start monitoring (blocking). Run in a thread."""
        if not self._available:
            raise RuntimeError("pyudev not available")

        import pyudev

        observer = pyudev.MonitorObserver(
            self._monitor,
            callback=lambda device: self._handle(device.action, device, on_add, on_remove),
        )
        observer.start()
        logger.info("Udev monitor started")

    def _handle(self, action: str, device, on_add, on_remove) -> None:
        """Route udev events to callbacks."""
        info = {
            "name": device.sys_name,
            "device_path": device.device_node,
            "serial": device.get("ID_SERIAL_SHORT"),
            "model": device.get("ID_MODEL"),
            "fstype": device.get("ID_FS_TYPE"),
            "label": device.get("ID_FS_LABEL"),
        }

        if action == "add" and on_add:
            logger.info("Disk added: %s", info["name"])
            on_add(info)
        elif action == "remove" and on_remove:
            logger.info("Disk removed: %s", info["name"])
            on_remove(info)
