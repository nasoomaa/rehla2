<?php

declare(strict_types=1);

namespace Rehla\Web\Providers;

use Illuminate\Support\ServiceProvider;

final class WebServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-web');
    }
}
