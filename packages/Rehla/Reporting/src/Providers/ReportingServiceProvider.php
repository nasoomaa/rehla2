<?php

declare(strict_types=1);

namespace Rehla\Reporting\Providers;

use Illuminate\Support\ServiceProvider;

final class ReportingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-reporting');
    }
}
