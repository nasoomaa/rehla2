<?php

declare(strict_types=1);

namespace Rehla\Api\Providers;

use Illuminate\Support\ServiceProvider;

final class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-api');
    }
}
