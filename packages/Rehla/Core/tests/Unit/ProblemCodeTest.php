<?php

declare(strict_types=1);

use Rehla\Core\Errors\ProblemCode;

it('publishes the canonical stable problem codes', function (): void {
    expect(array_column(ProblemCode::cases(), 'value'))->toBe([
        'wallet.insufficient_balance',
        'service.unavailable',
        'service.not_found',
        'service.price_changed',
        'service.fulfillment_policy_missing',
        'fulfillment.policy_immutable',
        'form.version_outdated',
        'form.validation_failed',
        'form.schema_integrity_failed',
        'form.not_found',
        'form.draft_not_found',
        'content.page_not_found',
        'content.slug_exists',
        'content.validation_failed',
        'traveler.passport_conflict',
        'traveler.not_found',
        'traveler.invalid_dates',
        'top_up.reference_used',
        'idempotency.key_reused',
        'operation.in_progress',
        'document.not_clean',
        'document.unsupported_type',
        'document.file_too_large',
        'document.verification_failed',
        'document.invalid_attachment',
        'auth.forbidden_resource',
    ]);

    foreach (ProblemCode::cases() as $code) {
        expect($code->value)->toMatch('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/');
    }
});
