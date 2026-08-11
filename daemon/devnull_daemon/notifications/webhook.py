"""Webhook notifier — calls Nextcloud DevNull app on hotplug events."""

import logging
from enum import Enum
from typing import Any

import httpx

from devnull_daemon.config import settings

logger = logging.getLogger(__name__)


class EventType(str, Enum):
    DISK_ADDED = "disk_added"
    DISK_REMOVED = "disk_removed"


class NextcloudWebhookNotifier:
    """Notifies the Nextcloud DevNull app when disks are plugged/unplugged.

    The PHP app exposes an internal endpoint:
        POST /ocs/v2.php/apps/devnull/api/v1/daemon/event

    This class calls that endpoint with the event payload.
    """

    def __init__(self) -> None:
        self._base_url = settings.nextcloud_url.rstrip("/")
        self._timeout = 5.0

    @property
    def _headers(self) -> dict[str, str]:
        return {
            "X-DevNull-Token": settings.auth_token,
            "OCS-APIREQUEST": "true",
            "Content-Type": "application/json",
        }

    def notify(self, event: EventType, disk_info: dict[str, Any]) -> bool:
        """Send event to Nextcloud. Returns True if accepted."""
        url = f"{self._base_url}/ocs/v2.php/apps/devnull/api/v1/daemon/event?format=json"
        payload = {
            "event": event.value,
            "disk": disk_info,
        }

        try:
            response = httpx.post(
                url,
                json=payload,
                headers=self._headers,
                timeout=self._timeout,
                verify=settings.nextcloud_verify_ssl,
            )
            if response.status_code == 200:
                logger.info("Webhook delivered: %s for %s", event.value, disk_info.get("name"))
                return True
            else:
                logger.warning(
                    "Webhook rejected: %s (HTTP %d)",
                    event.value,
                    response.status_code,
                )
                return False
        except httpx.RequestError as e:
            logger.error("Webhook failed: %s — %s", event.value, e)
            return False

    def notify_disk_added(self, disk_info: dict[str, Any]) -> bool:
        return self.notify(EventType.DISK_ADDED, disk_info)

    def notify_disk_removed(self, disk_info: dict[str, Any]) -> bool:
        return self.notify(EventType.DISK_REMOVED, disk_info)
