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
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type', 80);
            $table->jsonb('payload');
            $table->timestampTz('read_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6);

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_name', 120);
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 100);
            $table->unsignedSmallInteger('payload_version');
            $table->jsonb('payload');
            $table->string('deduplication_key', 255)->unique();
            $table->string('status', 20);
            $table->timestampTz('available_at', precision: 6);
            $table->timestampTz('locked_at', precision: 6)->nullable();
            $table->string('locked_by', 100)->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestampTz('lease_expires_at', precision: 6)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('delivered_at', precision: 6)->nullable();
            $table->timestampTz('dead_lettered_at', precision: 6)->nullable();
            $table->string('last_error', 255)->nullable();
            $table->string('last_trace_id', 100)->nullable();
            $table->timestampTz('created_at', precision: 6);

            $table->index(['status', 'available_at', 'id']);
            $table->index(['status', 'lease_expires_at', 'id']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE outbox_messages
            ADD CONSTRAINT outbox_messages_status_check
            CHECK (status IN ('available', 'locked', 'delivered', 'dead_letter')),
            ADD CONSTRAINT outbox_messages_attempts_check
            CHECK (attempts >= 0),
            ADD CONSTRAINT outbox_messages_claim_check
            CHECK (
                (status = 'locked' AND locked_at IS NOT NULL AND locked_by IS NOT NULL
                    AND lock_token IS NOT NULL AND lease_expires_at IS NOT NULL)
                OR
                (status <> 'locked' AND locked_at IS NULL AND locked_by IS NULL
                    AND lock_token IS NULL AND lease_expires_at IS NULL)
            ),
            ADD CONSTRAINT outbox_messages_terminal_check
            CHECK (
                (status = 'delivered' AND delivered_at IS NOT NULL AND dead_lettered_at IS NULL)
                OR (status = 'dead_letter' AND dead_lettered_at IS NOT NULL AND delivered_at IS NULL)
                OR (status IN ('available', 'locked') AND delivered_at IS NULL AND dead_lettered_at IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('notifications');
    }
};
