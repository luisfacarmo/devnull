<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Value object for mount operation result.
 */
final class MountResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $mountpoint,
        public readonly ?string $error,
    ) {
    }

    public static function success(string $mountpoint): self
    {
        return new self(true, $mountpoint, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
