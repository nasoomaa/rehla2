<?php

declare(strict_types=1);

namespace Rehla\Notifications\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class OutboxMessageData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventName,
        public string $aggregateType,
        public string $aggregateId,
        public int $payloadVersion,
        public array $payload,
        public string $deduplicationKey,
        public CarbonImmutable $availableAt,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $eventName) !== 1) {
            throw new InvalidArgumentException('Invalid outbox event name.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $aggregateType) !== 1) {
            throw new InvalidArgumentException('Invalid outbox aggregate type.');
        }
        if ($aggregateId === '' || strlen($aggregateId) > 100 || $payloadVersion < 1) {
            throw new InvalidArgumentException('Invalid outbox aggregate or payload version.');
        }
        if ($deduplicationKey === '' || strlen($deduplicationKey) > 255) {
            throw new InvalidArgumentException('Invalid outbox deduplication key.');
        }

        json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
