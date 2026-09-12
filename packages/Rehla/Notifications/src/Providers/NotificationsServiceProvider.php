<?php

declare(strict_types=1);

namespace Rehla\Notifications\Providers;

use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-notifications');
    }
}
