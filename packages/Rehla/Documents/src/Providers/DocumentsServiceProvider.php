<?php

declare(strict_types=1);

namespace Rehla\Documents\Providers;

use Illuminate\Support\ServiceProvider;

final class DocumentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-documents');
    }
}
