<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_catalog_immutable_change()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'catalog immutable records cannot be changed' USING ERRCODE = '23000';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER service_price_history_immutable
            BEFORE UPDATE OR DELETE ON service_price_history
            FOR EACH ROW EXECUTE FUNCTION reject_catalog_immutable_change();

            CREATE TRIGGER fulfillment_policy_versions_immutable
            BEFORE UPDATE OR DELETE ON fulfillment_policy_versions
            FOR EACH ROW EXECUTE FUNCTION reject_catalog_immutable_change();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS fulfillment_policy_versions_immutable ON fulfillment_policy_versions;
            DROP TRIGGER IF EXISTS service_price_history_immutable ON service_price_history;
            DROP FUNCTION IF EXISTS reject_catalog_immutable_change();
        SQL);
    }
};
