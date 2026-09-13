<?php

declare(strict_types=1);

namespace Rehla\Travelers\Data;

use Carbon\CarbonImmutable;
use Rehla\Travelers\Enums\Gender;

final readonly class TravelerSnapshot
{
    public function __construct(
        public string $id,
        public string $fullName,
        public CarbonImmutable $dateOfBirth,
        public Gender $gender,
        public string $passportNumber,
        public CarbonImmutable $passportIssuedAt,
        public CarbonImmutable $passportExpiresAt,
    ) {}
}
