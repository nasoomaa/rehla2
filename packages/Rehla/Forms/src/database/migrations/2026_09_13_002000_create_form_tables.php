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
        Schema::create('form_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id')->unique();
            $table->jsonb('schema');
            $table->uuid('current_version_id')->nullable()->unique();
            $table->uuid('updated_by');
            $table->timestampsTz(precision: 6);
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
        });

        Schema::create('form_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->unsignedBigInteger('version');
            $table->jsonb('schema');
            $table->char('checksum', 64);
            $table->string('status', 16)->default('published');
            $table->uuid('published_by');
            $table->timestampTz('published_at', precision: 6);
            $table->timestampTz('created_at', precision: 6);
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->unique(['service_id', 'version']);
        });

        Schema::table('form_drafts', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('form_versions')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE form_versions ADD CONSTRAINT form_versions_version_check CHECK (version > 0), ADD CONSTRAINT form_versions_status_check CHECK (status = 'published')");
    }

    public function down(): void
    {
        Schema::table('form_drafts', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('form_drafts');
    }
};
