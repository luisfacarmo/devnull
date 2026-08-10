# Disk Detection — Requirements

## Feature
Detect and list available block devices (unmounted external disks) connected to the server.

## Acceptance Criteria

1. The system lists all unmounted partitions with supported filesystems
2. Each disk entry includes: name, size, filesystem type, label, model
3. The detection responds within 2 seconds
4. Only block devices of type "part" (partition) are listed
5. Devices with existing mountpoints are excluded
6. The listing is refreshable on demand

## Technical Notes

- Uses `lsblk --json` for structured output
- No root required for detection (read-only operation)
- Must handle edge cases: no disks, corrupted partition tables, unknown filesystems
