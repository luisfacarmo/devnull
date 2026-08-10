<?php

declare(strict_types=1);

namespace OCA\DevNull\Capability;

/**
 * Value object for unmount operation result.
 */
final class UnmountResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $error,
    ) {
    }

    public static function success(): self
    {
        return new self(true, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, $error);
    }
}
