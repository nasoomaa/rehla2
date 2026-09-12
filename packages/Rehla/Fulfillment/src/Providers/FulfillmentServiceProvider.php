<?php

declare(strict_types=1);

namespace Rehla\Fulfillment\Providers;

use Illuminate\Support\ServiceProvider;

final class FulfillmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-fulfillment');
    }
}
