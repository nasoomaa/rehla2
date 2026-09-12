<?php

declare(strict_types=1);

use Rehla\Core\Money\Money;

it('keeps SDG arithmetic in integer minor units', function (): void {
    $balance = Money::sdg(5_000_00);
    $price = Money::sdg(2_500_00);

    expect($balance->subtract($price)->minor())->toBe(2_500_00)
        ->and($balance->currency())->toBe('SDG')
        ->and($price->add($price)->minor())->toBe(5_000_00)
        ->and($price->isLessThan($balance))->toBeTrue();
});

it('rejects negative values subtraction below zero and integer overflow', function (): void {
    expect(fn (): Money => Money::sdg(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Money => Money::sdg(100)->subtract(Money::sdg(101)))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Money => Money::sdg(PHP_INT_MAX)->add(Money::sdg(1)))->toThrow(OverflowException::class);
});

it('exposes no floating point money API', function (): void {
    $reflection = new ReflectionClass(Money::class);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        foreach ($method->getParameters() as $parameter) {
            expect((string) $parameter->getType())->not->toBe('float');
        }
        expect((string) $method->getReturnType())->not->toBe('float');
    }
});
