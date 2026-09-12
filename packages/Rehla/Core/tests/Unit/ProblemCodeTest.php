<?php

declare(strict_types=1);

use Rehla\Core\Errors\ProblemCode;

it('publishes the canonical stable problem codes', function (): void {
    expect(array_column(ProblemCode::cases(), 'value'))->toBe([
        'wallet.insufficient_balance',
        'service.unavailable',
        'service.price_changed',
        'form.version_changed',
        'traveler.passport_conflict',
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
