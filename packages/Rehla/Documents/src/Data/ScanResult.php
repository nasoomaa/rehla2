<?php

declare(strict_types=1);

namespace Rehla\Documents\Data;

use InvalidArgumentException;

final readonly class ScanResult
{
    private function __construct(
        public bool $clean,
        public ?string $rejectionCode,
    ) {
        if ($clean === ($rejectionCode !== null)) {
            throw new InvalidArgumentException;
        }

        if ($rejectionCode !== null && preg_match('/^[a-z][a-z0-9_]*$/', $rejectionCode) !== 1) {
            throw new InvalidArgumentException;
        }
    }

    public static function clean(): self
    {
        return new self(true, null);
    }

    public static function rejected(string $code): self
    {
        return new self(false, $code);
    }
}
