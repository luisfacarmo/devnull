"""Daemon entrypoint."""

import logging
import uvicorn

from devnull_daemon.config import settings
from devnull_daemon.api.server import create_app


def run() -> None:
    """Start the daemon server."""
    logging.basicConfig(
        level=getattr(logging, settings.log_level.upper(), logging.INFO),
        format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    )

    app = create_app()

    uvicorn.run(
        app,
        host=settings.host,
        port=settings.port,
        log_level=settings.log_level.lower(),
    )


if __name__ == "__main__":
    run()
