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
        Schema::create('upload_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->string('purpose', 40);
            $table->timestampTz('expires_at', precision: 6);
            $table->timestampTz('claimed_at', precision: 6)->nullable();
            $table->timestampsTz(precision: 6);

            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['owner_id', 'expires_at']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('upload_session_id')->unique();
            $table->uuid('owner_id');
            $table->string('purpose', 40);
            $table->string('disk', 40);
            $table->string('storage_key', 255)->nullable()->unique();
            $table->string('original_name', 255);
            $table->string('declared_mime', 80);
            $table->string('detected_mime', 80)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('status', 24);
            $table->string('rejection_code', 80)->nullable();
            $table->uuid('scan_token')->nullable();
            $table->timestampTz('scan_lease_expires_at', precision: 6)->nullable();
            $table->uuid('cleanup_claim_token')->nullable();
            $table->timestampTz('cleanup_lease_expires_at', precision: 6)->nullable();
            $table->timestampTz('scanned_at', precision: 6)->nullable();
            $table->timestampTz('attached_at', precision: 6)->nullable();
            $table->timestampTz('purged_at', precision: 6)->nullable();
            $table->timestampsTz(precision: 6);

            $table->foreign('upload_session_id')->references('id')->on('upload_sessions')->restrictOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['owner_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'cleanup_lease_expires_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE upload_sessions
            ADD CONSTRAINT upload_sessions_purpose_check
            CHECK (purpose IN (
                'bank_receipt', 'passport_scan', 'identity_document',
                'applicant_photo', 'supporting_document', 'issued_document'
            ))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ADD CONSTRAINT documents_purpose_check
            CHECK (purpose IN (
                'bank_receipt', 'passport_scan', 'identity_document',
                'applicant_photo', 'supporting_document', 'issued_document'
            )),
            ADD CONSTRAINT documents_status_check
            CHECK (status IN (
                'pending_scan', 'quarantined', 'clean', 'rejected',
                'attached', 'cleanup_claimed', 'purged'
            )),
            ADD CONSTRAINT documents_private_disk_check
            CHECK (disk = 'private'),
            ADD CONSTRAINT documents_purged_storage_check
            CHECK (
                (status = 'purged' AND storage_key IS NULL AND purged_at IS NOT NULL)
                OR (status <> 'purged' AND storage_key IS NOT NULL AND purged_at IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('upload_sessions');
    }
};
