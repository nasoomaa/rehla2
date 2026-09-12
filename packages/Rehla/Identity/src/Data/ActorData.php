<?php

declare(strict_types=1);

namespace Rehla\Identity\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class ActorData
{
    public function __construct(
        public string $id,
        public string $type,
        public ?CarbonImmutable $mfaConfirmedAt = null,
    ) {
        OpaqueId::fromString($id);

        if (! in_array($type, ['customer', 'staff'], true)) {
            throw new InvalidArgumentException('Identity actor type must be customer or staff.');
        }
    }
}
