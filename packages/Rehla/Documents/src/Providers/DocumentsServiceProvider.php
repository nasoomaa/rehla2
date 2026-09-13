<?php

declare(strict_types=1);

namespace Rehla\Documents\Providers;

use Illuminate\Support\ServiceProvider;
use Rehla\Documents\Actions\AttachDocument;
use Rehla\Documents\Actions\AttachPublicDocuments;
use Rehla\Documents\Contracts\DocumentDownloadAuthorizer;
use Rehla\Documents\Contracts\DocumentScanner;
use Rehla\Documents\Contracts\OwnedDocuments;
use Rehla\Documents\Contracts\PublicDocuments;
use Rehla\Documents\Infrastructure\ClamAvDocumentScanner;
use Rehla\Documents\Queries\AuthorizeDocumentDownload;

final class DocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/documents.php', 'rehla-documents');
        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);
        $this->app->bind(OwnedDocuments::class, AttachDocument::class);
        $this->app->bind(PublicDocuments::class, AttachPublicDocuments::class);
        $this->app->bind(DocumentDownloadAuthorizer::class, AuthorizeDocumentDownload::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-documents');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
