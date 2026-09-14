<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 160)->unique();
            $table->string('title_en', 150);
            $table->string('title_ar', 150);
            $table->text('body_en');
            $table->text('body_ar');
            $table->string('meta_title_en', 150)->default('');
            $table->string('meta_title_ar', 150)->default('');
            $table->string('meta_description_en', 320)->default('');
            $table->string('meta_description_ar', 320)->default('');
            $table->string('status', 20)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->uuid('updated_by');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE content_pages ADD CONSTRAINT content_pages_status_check CHECK (status IN ('draft', 'published'))");
        DB::statement("ALTER TABLE content_pages ADD CONSTRAINT content_pages_slug_format_check CHECK (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
