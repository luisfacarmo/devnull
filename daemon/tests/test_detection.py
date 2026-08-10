"""Tests for disk detection."""

import json
from unittest.mock import patch, MagicMock

from devnull_daemon.detection.poller import list_available_disks


class TestListAvailableDisks:
    """Tests for lsblk-based detection."""

    def test_returns_unmounted_partitions(self):
        mock_output = json.dumps({
            "blockdevices": [
                {"name": "sda1", "size": "500G", "fstype": "ext4", "label": "root",
                 "mountpoint": "/", "type": "part", "serial": None, "model": None},
                {"name": "sdb1", "size": "1T", "fstype": "ntfs", "label": "Backup",
                 "mountpoint": None, "type": "part", "serial": "WD-123", "model": "WD Elements"},
            ]
        })

        with patch("subprocess.run") as mock_run:
            mock_run.return_value = MagicMock(returncode=0, stdout=mock_output)
            disks = list_available_disks()

        assert len(disks) == 1
        assert disks[0].name == "sdb1"
        assert disks[0].fstype == "ntfs"
        assert disks[0].serial == "WD-123"

    def test_returns_empty_on_failure(self):
        with patch("subprocess.run") as mock_run:
            mock_run.return_value = MagicMock(returncode=1, stderr="error")
            disks = list_available_disks()

        assert disks == []

    def test_skips_devices_without_fstype(self):
        mock_output = json.dumps({
            "blockdevices": [
                {"name": "sdb", "size": "1T", "fstype": None, "label": None,
                 "mountpoint": None, "type": "disk", "serial": None, "model": None},
            ]
        })

        with patch("subprocess.run") as mock_run:
            mock_run.return_value = MagicMock(returncode=0, stdout=mock_output)
            disks = list_available_disks()

        assert disks == []
