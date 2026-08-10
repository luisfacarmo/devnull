# Mount System — Requirements

## Feature
Mount/unmount external disks and register them as Nextcloud external storage.

## Acceptance Criteria

1. Admin can mount a detected disk with one click
2. Mount uses udisksctl (no root required with polkit rule)
3. Mounted disk is automatically registered as Nextcloud external storage
4. Admin can unmount/eject a mounted disk
5. Unmounting removes the external storage registration
6. Device names are validated (only alphanumeric, no path traversal)
7. Only one mount operation can run at a time per device (mutex)
8. Operations are logged with timestamp and result

## Technical Notes

- Polkit rule required for www-data to use udisksctl
- Mount path: /media/devnull/<device>
- External storage registration via files_external PHP API or OCC
- Decision D3 pending: which approach for external storage registration
