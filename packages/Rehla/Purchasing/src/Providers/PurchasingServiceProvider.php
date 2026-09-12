<?php

declare(strict_types=1);

namespace Rehla\Purchasing\Providers;

use Illuminate\Support\ServiceProvider;

final class PurchasingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-purchasing');
    }
}
