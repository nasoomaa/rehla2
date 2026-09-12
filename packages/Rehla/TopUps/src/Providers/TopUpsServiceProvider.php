<?php

declare(strict_types=1);

namespace Rehla\TopUps\Providers;

use Illuminate\Support\ServiceProvider;

final class TopUpsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-topups');
    }
}
