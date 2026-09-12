<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Rehla\Core\Providers\CoreServiceProvider;
use Rehla\Core\Time\Clock;
use Rehla\Core\Time\SystemClock;

it('returns immutable UTC instants and binds the clock contract', function (): void {
    $application = new Application(dirname(__DIR__, 5));
    (new CoreServiceProvider($application))->register();
    $clock = $application->make(Clock::class);
    $now = $clock->now();

    expect($clock)->toBeInstanceOf(SystemClock::class)
        ->and($now)->toBeInstanceOf(CarbonImmutable::class)
        ->and($now->getTimezone()->getName())->toBe('UTC');
});
