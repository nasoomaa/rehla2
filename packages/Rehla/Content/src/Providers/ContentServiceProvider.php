<?php

declare(strict_types=1);

namespace Rehla\Content\Providers;

use Illuminate\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-content');
    }
}
