<?php

declare(strict_types=1);

namespace Rehla\Audit\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Audit\Actions\AppendAuditEntry;
use Rehla\Audit\Contracts\AuditWriter;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditWriter::class, AppendAuditEntry::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-audit');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
