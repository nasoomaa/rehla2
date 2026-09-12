<?php

declare(strict_types=1);

namespace Rehla\Travelers\Providers;

use Illuminate\Support\ServiceProvider;

final class TravelersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-travelers');
    }
}
