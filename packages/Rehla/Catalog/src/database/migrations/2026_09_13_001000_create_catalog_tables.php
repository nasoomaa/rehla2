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
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 120)->unique();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->string('short_description_en', 500);
            $table->string('short_description_ar', 500);
            $table->text('detailed_description_en');
            $table->text('detailed_description_ar');
            $table->string('expected_duration_en', 160);
            $table->string('expected_duration_ar', 160);
            $table->text('notes_en')->nullable();
            $table->text('notes_ar')->nullable();
            $table->unsignedBigInteger('current_price_minor');
            $table->char('currency', 3)->default('SDG');
            $table->unsignedBigInteger('price_version')->default(1);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('published_at', precision: 6)->nullable();
            $table->timestampsTz(precision: 6);
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('service_price_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('version');
            $table->uuid('changed_by');
            $table->timestampTz('effective_at', precision: 6);
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->unique(['service_id', 'version']);
        });

        Schema::create('service_requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->text('text_en');
            $table->text('text_ar');
            $table->unsignedInteger('sort_order');
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->unique(['service_id', 'sort_order']);
        });

        Schema::create('service_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->uuid('document_id')->unique();
            $table->string('alt_en', 255);
            $table->string('alt_ar', 255);
            $table->unsignedInteger('sort_order');
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->restrictOnDelete();
            $table->unique(['service_id', 'sort_order']);
        });

        Schema::create('fulfillment_policy_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id')->unique();
            $table->jsonb('policy');
            $table->uuid('updated_by');
            $table->timestampsTz(precision: 6);
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
        });

        Schema::create('fulfillment_policy_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->unsignedBigInteger('version');
            $table->jsonb('policy');
            $table->char('checksum', 64);
            $table->uuid('published_by');
            $table->timestampTz('published_at', precision: 6);
            $table->timestampTz('created_at', precision: 6);
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->unique(['service_id', 'version']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE services
            ADD CONSTRAINT services_slug_check CHECK (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'),
            ADD CONSTRAINT services_price_check CHECK (current_price_minor > 0),
            ADD CONSTRAINT services_currency_check CHECK (currency = 'SDG'),
            ADD CONSTRAINT services_price_version_check CHECK (price_version > 0),
            ADD CONSTRAINT services_status_check CHECK (status IN ('draft', 'active', 'inactive'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE service_price_history
            ADD CONSTRAINT service_price_history_price_check CHECK (price_minor > 0),
            ADD CONSTRAINT service_price_history_currency_check CHECK (currency = 'SDG'),
            ADD CONSTRAINT service_price_history_version_check CHECK (version > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_policy_versions');
        Schema::dropIfExists('fulfillment_policy_drafts');
        Schema::dropIfExists('service_media');
        Schema::dropIfExists('service_requirements');
        Schema::dropIfExists('service_price_history');
        Schema::dropIfExists('services');
    }
};
