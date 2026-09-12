<?php

declare(strict_types=1);

namespace Rehla\Integrations\Providers;

use Illuminate\Support\ServiceProvider;

final class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-integrations');
    }
}
