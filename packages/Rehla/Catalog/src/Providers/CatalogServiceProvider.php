<?php

declare(strict_types=1);

namespace Rehla\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Catalog\Contracts\PublishedFulfillmentPolicyReader;
use Rehla\Catalog\Contracts\ServiceCatalog;
use Rehla\Catalog\Contracts\ServiceQuoteReader;
use Rehla\Catalog\Queries\GetCurrentServiceQuote;
use Rehla\Catalog\Queries\GetPublishedFulfillmentPolicy;
use Rehla\Catalog\Queries\ListPublishedServices;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServiceCatalog::class, ListPublishedServices::class);
        $this->app->bind(ServiceQuoteReader::class, GetCurrentServiceQuote::class);
        $this->app->bind(PublishedFulfillmentPolicyReader::class, GetPublishedFulfillmentPolicy::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-catalog');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
