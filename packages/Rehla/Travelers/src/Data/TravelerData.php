<?php

declare(strict_types=1);

namespace Rehla\Travelers\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Travelers\Enums\Gender;
use Rehla\Travelers\Exceptions\InvalidTraveler;

final readonly class TravelerData
{
    public function __construct(
        public string $ownerId,
        public string $fullName,
        public CarbonImmutable $dateOfBirth,
        public Gender $gender,
        public string $passportNumber,
        public CarbonImmutable $passportIssuedAt,
        public CarbonImmutable $passportExpiresAt,
    ) {
        OpaqueId::fromString($ownerId);
        $nameLength = mb_strlen(trim($fullName));
        if ($nameLength === 0 || $nameLength > 100) {
            throw new InvalidArgumentException('Traveler name must contain between 1 and 100 characters.');
        }

        $today = CarbonImmutable::now('UTC')->startOfDay();
        if (! $dateOfBirth->isBefore($today)
            || $dateOfBirth->isBefore($today->subYears(120))
            || ! $passportIssuedAt->isAfter($dateOfBirth)
            || ! $passportIssuedAt->isBefore($today)
            || ! $passportExpiresAt->isAfter($passportIssuedAt)
            || ! $passportExpiresAt->isAfter($today)) {
            throw new InvalidTraveler;
        }
    }
}
