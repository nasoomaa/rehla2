<?php

declare(strict_types=1);

namespace Rehla\Orders\Providers;

use Illuminate\Support\ServiceProvider;

final class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-orders');
    }
}
