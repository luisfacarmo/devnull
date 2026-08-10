"""Configuration via environment variables (Pydantic Settings)."""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Daemon configuration loaded from .env or environment."""

    # Server
    host: str = "127.0.0.1"
    port: int = 9876

    # Auth
    auth_token: str = "change-me-to-a-secure-random-string"

    # Detection
    detection_mode: str = "udev"  # "udev" or "polling"
    poll_interval: int = 5  # seconds

    # Mount
    mount_base: str = "/media/devnull"
    mount_strategy: str = "udisks"  # "udisks" or "sudo"

    # Logging
    log_level: str = "INFO"

    model_config = {
        "env_prefix": "DEVNULL_",
        "env_file": ".env",
        "env_file_encoding": "utf-8",
    }


settings = Settings()
