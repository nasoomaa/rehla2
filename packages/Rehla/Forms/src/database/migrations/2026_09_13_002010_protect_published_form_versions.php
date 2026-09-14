<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_form_version_change()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'published form versions cannot be changed' USING ERRCODE = '23000';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER form_versions_immutable
            BEFORE UPDATE OR DELETE ON form_versions
            FOR EACH ROW EXECUTE FUNCTION reject_form_version_change();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS form_versions_immutable ON form_versions;
            DROP FUNCTION IF EXISTS reject_form_version_change();
        SQL);
    }
};
