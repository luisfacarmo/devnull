# Scan Pipeline — Requirements (Phase 2)

## Feature
Trigger file scanning, deduplication, and AI classification on mounted disks.

## Acceptance Criteria

1. Admin can trigger a file scan on a mounted disk
2. Scan runs as a Nextcloud Background Job (never synchronous)
3. Progress is visible in the frontend (polling-based)
4. After scan, admin can trigger Duplicate Finder analysis
5. After scan, admin can trigger Recognize classification
6. Notifications are sent when operations complete
7. Multiple operations can be queued (sequential execution)

## Technical Notes

- Scan uses `occ files:scan --path=<external_storage_path>`
- Duplicate Finder and Recognize have their own background jobs
- Need to research: can we trigger their jobs programmatically?
- SSE for real-time progress is a Phase 3 enhancement
