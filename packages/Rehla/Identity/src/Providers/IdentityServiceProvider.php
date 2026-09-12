<?php

declare(strict_types=1);

namespace Rehla\Identity\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Rehla\Identity\Actions\AuthorizeActor;
use Rehla\Identity\Auth\CustomerEloquentUserProvider;
use Rehla\Identity\Auth\StaffEloquentUserProvider;
use Rehla\Identity\Contracts\AuthorizesActor;
use Rehla\Identity\Contracts\IdentityReader;
use Rehla\Identity\Queries\FindCustomer;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthorizesActor::class, AuthorizeActor::class);
        $this->app->bind(IdentityReader::class, FindCustomer::class);
    }

    public function boot(): void
    {
        Auth::provider('rehla-customer', static function (Application $app, array $config): CustomerEloquentUserProvider {
            return new CustomerEloquentUserProvider($app->make(Hasher::class), (string) $config['model']);
        });
        Auth::provider('rehla-staff', static function (Application $app, array $config): StaffEloquentUserProvider {
            return new StaffEloquentUserProvider($app->make(Hasher::class), (string) $config['model']);
        });

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-identity');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
