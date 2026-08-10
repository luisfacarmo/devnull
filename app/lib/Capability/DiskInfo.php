<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Value object representing a detected block device.
 */
final class DiskInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $size,
        public readonly ?string $fstype,
        public readonly ?string $label,
        public readonly ?string $mountpoint,
        public readonly ?string $serial,
        public readonly ?string $model,
    ) {
    }

    public function isMounted(): bool
    {
        return !empty($this->mountpoint);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'size' => $this->size,
            'fstype' => $this->fstype,
            'label' => $this->label,
            'mountpoint' => $this->mountpoint,
            'serial' => $this->serial,
            'model' => $this->model,
            'mounted' => $this->isMounted(),
        ];
    }
}
