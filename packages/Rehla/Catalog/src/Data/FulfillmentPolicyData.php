<?php

declare(strict_types=1);

namespace Rehla\Catalog\Data;

use Carbon\CarbonImmutable;
use Rehla\Catalog\Exceptions\FulfillmentPolicyInvalid;

final readonly class FulfillmentPolicyData
{
    private const array ALLOWED_TRANSITIONS = [
        'received:under_review', 'received:processing',
        'under_review:processing', 'processing:under_review',
        'under_review:action_required', 'processing:action_required',
        'action_required:action_received',
        'action_received:under_review', 'action_received:processing',
        'under_review:completed', 'processing:completed', 'action_received:completed',
        'received:cancelled', 'under_review:cancelled', 'processing:cancelled',
        'action_required:cancelled', 'action_received:cancelled',
    ];

    /** @param array<string, mixed> $policy */
    private function __construct(
        public array $policy,
        public ?string $id,
        public ?string $serviceId,
        public ?int $version,
        public ?string $checksum,
        public ?CarbonImmutable $publishedAt,
    ) {
        self::assertValid($policy);
    }

    /** @param array<string, mixed> $policy */
    public static function draft(array $policy): self
    {
        return new self($policy, null, null, null, null, null);
    }

    /** @param array<string, mixed> $policy */
    public static function published(
        array $policy,
        string $id,
        string $serviceId,
        int $version,
        string $checksum,
        CarbonImmutable $publishedAt,
    ): self {
        return new self($policy, $id, $serviceId, $version, $checksum, $publishedAt);
    }

    /** @param array<string, mixed> $policy */
    private static function assertValid(array $policy): void
    {
        $transitions = $policy['transitions'] ?? null;
        $guidance = $policy['guidance'] ?? null;
        if (! is_array($transitions) || $transitions === []
            || ! is_bool($policy['completion_requires_document'] ?? null)
            || ! is_array($guidance)) {
            throw new FulfillmentPolicyInvalid;
        }

        foreach (['staff', 'customer'] as $audience) {
            $localized = $guidance[$audience] ?? null;
            if (! is_array($localized)
                || ! is_string($localized['en'] ?? null) || trim($localized['en']) === ''
                || ! is_string($localized['ar'] ?? null) || trim($localized['ar']) === '') {
                throw new FulfillmentPolicyInvalid;
            }
        }

        $seen = [];
        foreach ($transitions as $transition) {
            if (! is_array($transition)
                || ! is_string($transition['from'] ?? null)
                || ! is_string($transition['to'] ?? null)
                || ! in_array($transition['actor'] ?? null, ['staff', 'customer', 'system'], true)
                || ! is_bool($transition['requires_reason'] ?? null)
                || ! is_bool($transition['requires_note'] ?? null)) {
                throw new FulfillmentPolicyInvalid;
            }
            $edge = $transition['from'].':'.$transition['to'];
            if (! in_array($edge, self::ALLOWED_TRANSITIONS, true) || isset($seen[$edge])) {
                throw new FulfillmentPolicyInvalid;
            }
            $seen[$edge] = true;
        }
    }
}
