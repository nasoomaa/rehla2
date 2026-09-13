<?php

declare(strict_types=1);

namespace Rehla\Notifications\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Identity\Contracts\RegistrationNotificationRecorder;
use Rehla\Notifications\Actions\AppendOutboxMessage;
use Rehla\Notifications\Actions\CreateInAppNotification;
use Rehla\Notifications\Contracts\NotificationReader;
use Rehla\Notifications\Contracts\NotificationRecorder;
use Rehla\Notifications\Contracts\OutboxWriter;
use Rehla\Notifications\Infrastructure\IdentityRegistrationNotificationRecorder;
use Rehla\Notifications\Queries\ListOwnedNotifications;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/notifications.php', 'rehla-notifications');
        $this->app->bind(OutboxWriter::class, AppendOutboxMessage::class);
        $this->app->bind(NotificationRecorder::class, CreateInAppNotification::class);
        $this->app->bind(NotificationReader::class, ListOwnedNotifications::class);
        $this->app->bind(RegistrationNotificationRecorder::class, IdentityRegistrationNotificationRecorder::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-notifications');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
