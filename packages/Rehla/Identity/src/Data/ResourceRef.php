<?php

declare(strict_types=1);

namespace Rehla\Identity\Data;

use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class ResourceRef
{
    public function __construct(
        public string $type,
        public string $id,
        public ?string $ownerAccountId = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $type) !== 1) {
            throw new InvalidArgumentException('Invalid resource type.');
        }

        OpaqueId::fromString($id);

        if ($ownerAccountId !== null) {
            OpaqueId::fromString($ownerAccountId);
        }
    }
}
