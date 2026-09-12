<?php

declare(strict_types=1);

namespace Rehla\Audit\Data;

use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class AppendAuditData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $oldState
     * @param  array<string, mixed>|null  $newState
     */
    public function __construct(
        public string $actorType,
        public ?string $actorId,
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public array $metadata,
        public ?array $oldState,
        public ?array $newState,
        public ?string $reason,
        public string $correlationId,
        public ?string $ipHash = null,
        public ?string $userAgentHash = null,
    ) {
        if (! in_array($actorType, ['staff', 'customer', 'system'], true)) {
            throw new InvalidArgumentException('Invalid audit actor type.');
        }

        if (($actorType === 'system') !== ($actorId === null)) {
            throw new InvalidArgumentException('System actors must be anonymous and human actors must have an identifier.');
        }

        if ($actorId !== null) {
            OpaqueId::fromString($actorId);
        }

        OpaqueId::fromString($subjectId);
        OpaqueId::fromString($correlationId);

        if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $action) !== 1) {
            throw new InvalidArgumentException('Audit actions use lowercase dot notation.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $subjectType) !== 1) {
            throw new InvalidArgumentException('Invalid audit subject type.');
        }
    }
}
