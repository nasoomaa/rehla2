<?php

declare(strict_types=1);

namespace Rehla\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Core\Time\Clock;
use Rehla\Core\Time\SystemClock;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-core');
    }
}
