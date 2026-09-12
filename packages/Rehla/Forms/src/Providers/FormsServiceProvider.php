<?php

declare(strict_types=1);

namespace Rehla\Forms\Providers;

use Illuminate\Support\ServiceProvider;

final class FormsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-forms');
    }
}
