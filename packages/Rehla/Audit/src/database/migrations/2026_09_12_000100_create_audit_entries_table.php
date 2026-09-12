<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type', 16);
            $table->uuid('actor_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 80);
            $table->uuid('subject_id');
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('old_state')->nullable();
            $table->jsonb('new_state')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('correlation_id');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestampTz('occurred_at', precision: 6);

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        // Immutable audit history is removed only by an explicit recovery procedure.
    }
};
