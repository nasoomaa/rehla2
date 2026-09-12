<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rehla_prevent_audit_entries_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_entries is append-only: updates and deletes are prohibited';
            END;
            $$;

            CREATE TRIGGER audit_entries_append_only
            BEFORE UPDATE OR DELETE ON audit_entries
            FOR EACH ROW
            EXECUTE FUNCTION rehla_prevent_audit_entries_mutation();
        SQL);
    }

    public function down(): void
    {
        // Removing immutability requires an explicit forward recovery migration.
    }
};
