<?php

declare(strict_types=1);

namespace Rehla\Content\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Content\Actions\ContentAdminActions;
use Rehla\Content\Contracts\ContentAdminCommands;
use Rehla\Content\Contracts\PublishedContentReader;
use Rehla\Content\Queries\GetPublishedPage;

final class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublishedContentReader::class, GetPublishedPage::class);
        $this->app->bind(ContentAdminCommands::class, ContentAdminActions::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-content');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
