"""Event bus — in-memory pub/sub for SSE streaming to frontend clients."""

import asyncio
import logging
import time
from dataclasses import dataclass, field
from typing import Any

logger = logging.getLogger(__name__)


@dataclass
class DiskEvent:
    """A single event to be streamed to clients."""

    event_type: str  # "disk_added", "disk_removed", "mount_complete", "error"
    data: dict[str, Any]
    timestamp: float = field(default_factory=time.time)


class EventBus:
    """Simple async event bus for Server-Sent Events.

    Producers call `publish()` (thread-safe via call_soon_threadsafe).
    Consumers iterate via `subscribe()` (async generator).
    """

    def __init__(self, max_history: int = 50) -> None:
        self._subscribers: list[asyncio.Queue[DiskEvent]] = []
        self._history: list[DiskEvent] = []
        self._max_history = max_history
        self._loop: asyncio.AbstractEventLoop | None = None

    def set_loop(self, loop: asyncio.AbstractEventLoop) -> None:
        """Set the asyncio event loop (called from FastAPI startup)."""
        self._loop = loop

    def publish(self, event: DiskEvent) -> None:
        """Publish an event to all subscribers (thread-safe)."""
        self._history.append(event)
        if len(self._history) > self._max_history:
            self._history = self._history[-self._max_history:]

        if self._loop is not None and self._loop.is_running():
            self._loop.call_soon_threadsafe(self._dispatch, event)
        else:
            # No loop yet or not running — just store in history
            logger.debug("Event stored (no active subscribers): %s", event.event_type)

    def _dispatch(self, event: DiskEvent) -> None:
        """Dispatch event to all subscriber queues."""
        dead: list[asyncio.Queue] = []
        for queue in self._subscribers:
            try:
                queue.put_nowait(event)
            except asyncio.QueueFull:
                dead.append(queue)

        for q in dead:
            self._subscribers.remove(q)

    async def subscribe(self, last_event_id: float = 0.0):
        """Async generator yielding events. Replays history if last_event_id provided."""
        queue: asyncio.Queue[DiskEvent] = asyncio.Queue(maxsize=100)
        self._subscribers.append(queue)

        try:
            # Replay missed events from history
            for event in self._history:
                if event.timestamp > last_event_id:
                    yield event

            # Stream live events
            while True:
                event = await queue.get()
                yield event
        finally:
            if queue in self._subscribers:
                self._subscribers.remove(queue)

    @property
    def history(self) -> list[DiskEvent]:
        return list(self._history)


# Singleton event bus
event_bus = EventBus()
