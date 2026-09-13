<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE upload_sessions DROP CONSTRAINT upload_sessions_purpose_check');
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_purpose_check');
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_private_disk_check');
        DB::statement(<<<'SQL'
            ALTER TABLE upload_sessions ADD CONSTRAINT upload_sessions_purpose_check
            CHECK (purpose IN (
                'bank_receipt', 'passport_scan', 'identity_document',
                'applicant_photo', 'supporting_document', 'issued_document',
                'service_media', 'bank_logo'
            ))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT documents_purpose_check
            CHECK (purpose IN (
                'bank_receipt', 'passport_scan', 'identity_document',
                'applicant_photo', 'supporting_document', 'issued_document',
                'service_media', 'bank_logo'
            )), ADD CONSTRAINT documents_disk_visibility_check CHECK (
                (purpose NOT IN ('service_media', 'bank_logo') AND disk = 'private')
                OR (purpose IN ('service_media', 'bank_logo') AND (
                    (status IN ('clean', 'attached') AND disk = 'public')
                    OR (status NOT IN ('clean', 'attached') AND disk IN ('private', 'public'))
                ))
            );
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE upload_sessions DROP CONSTRAINT upload_sessions_purpose_check');
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_purpose_check');
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_disk_visibility_check');
        DB::statement(<<<'SQL'
            ALTER TABLE upload_sessions ADD CONSTRAINT upload_sessions_purpose_check
            CHECK (purpose IN (
                'bank_receipt', 'passport_scan', 'identity_document',
                'applicant_photo', 'supporting_document', 'issued_document'
            ))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT documents_purpose_check
            CHECK (purpose IN (
                'bank_receipt', 'passport_scan', 'identity_document',
                'applicant_photo', 'supporting_document', 'issued_document'
            )), ADD CONSTRAINT documents_private_disk_check CHECK (disk = 'private');
        SQL);
    }
};
