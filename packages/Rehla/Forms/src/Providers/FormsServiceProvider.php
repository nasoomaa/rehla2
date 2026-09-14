<?php

declare(strict_types=1);

namespace Rehla\Forms\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Forms\Actions\FormAdminActions;
use Rehla\Forms\Contracts\FormAdminCommands;
use Rehla\Forms\Contracts\FormSubmissionValidator;
use Rehla\Forms\Contracts\PublishedFormReader;
use Rehla\Forms\Queries\GetPublishedForm;
use Rehla\Forms\Queries\ValidateFormSubmission;

final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublishedFormReader::class, GetPublishedForm::class);
        $this->app->bind(FormSubmissionValidator::class, ValidateFormSubmission::class);
        $this->app->bind(FormAdminCommands::class, FormAdminActions::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-forms');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
