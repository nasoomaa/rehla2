<?php

declare(strict_types=1);

namespace Rehla\Notifications\Data;

use Carbon\CarbonImmutable;

final readonly class OutboxEnvelope
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $eventName,
        public string $aggregateType,
        public string $aggregateId,
        public int $payloadVersion,
        public array $payload,
        public string $deduplicationKey,
        public string $workerId,
        public string $lockToken,
        public CarbonImmutable $leaseExpiresAt,
        public int $attempts,
    ) {}
}
