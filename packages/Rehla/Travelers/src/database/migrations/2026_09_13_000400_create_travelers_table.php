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
        Schema::create('travelers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->string('full_name', 100);
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('passport_number', 64);
            $table->string('normalized_passport_number', 12)->unique();
            $table->date('passport_issued_at');
            $table->date('passport_expires_at');
            $table->timestampsTz(precision: 6);

            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['owner_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE travelers
            ADD CONSTRAINT travelers_gender_check
            CHECK (gender IN ('male', 'female')),
            ADD CONSTRAINT travelers_passport_format_check
            CHECK (normalized_passport_number ~ '^[A-Z0-9]{6,12}$'),
            ADD CONSTRAINT travelers_date_order_check
            CHECK (
                passport_issued_at > date_of_birth
                AND passport_expires_at > passport_issued_at
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('travelers');
    }
};
