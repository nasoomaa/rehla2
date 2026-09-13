<?php

declare(strict_types=1);

namespace Rehla\Travelers\Support;

use InvalidArgumentException;

final class NormalizePassportNumber
{
    public function normalize(string $passportNumber): string
    {
        $normalized = preg_replace('/[\p{Z}\s\-_\/.]+/u', '', mb_strtoupper($passportNumber));
        if (! is_string($normalized) || preg_match('/^[A-Z0-9]{6,12}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Invalid passport number.');
        }

        return $normalized;
    }
}
