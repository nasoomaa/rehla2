<?php

declare(strict_types=1);

namespace Rehla\Travelers\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Travelers\Contracts\TravelerReader;
use Rehla\Travelers\Contracts\TravelerSnapshotReader;
use Rehla\Travelers\Queries\GetOwnedTravelerSnapshot;
use Rehla\Travelers\Queries\ListOwnedTravelers;

final class TravelersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TravelerReader::class, ListOwnedTravelers::class);
        $this->app->bind(TravelerSnapshotReader::class, GetOwnedTravelerSnapshot::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-travelers');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
