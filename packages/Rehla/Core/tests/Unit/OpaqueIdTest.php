<?php

declare(strict_types=1);

use Rehla\Core\Identifiers\OpaqueId;

it('generates an opaque UUID v4 identifier', function (): void {
    $id = OpaqueId::generate();

    expect($id->value())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and((string) $id)->toBe($id->value());
});

it('normalizes and compares existing identifiers', function (): void {
    $id = OpaqueId::fromString('550E8400-E29B-41D4-A716-446655440000');

    expect($id->value())->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($id->equals(OpaqueId::fromString($id->value())))->toBeTrue()
        ->and($id->equals(OpaqueId::generate()))->toBeFalse();
});

it('rejects malformed and non RFC variant identifiers', function (string $value): void {
    expect(fn (): OpaqueId => OpaqueId::fromString($value))->toThrow(InvalidArgumentException::class);
})->with([
    'malformed' => 'not-a-uuid',
    'wrong version' => '550e8400-e29b-11d4-a716-446655440000',
    'wrong variant' => '550e8400-e29b-41d4-0716-446655440000',
]);
