<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Rehla\Identity\Enums\AbilityName;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 255);
            $table->string('password');
            $table->string('status', 16)->default('active');
            $table->char('preferred_locale', 2)->default('en');
            $table->timestampTz('email_verified_at', precision: 6)->nullable();
            $table->rememberToken();
            $table->timestampsTz(precision: 6);
        });
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email');
            $table->index('status');
        });

        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->string('department', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('mfa_confirmed_at', precision: 6)->nullable();
            $table->timestampsTz(precision: 6);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->timestampsTz(precision: 6);
        });

        Schema::create('abilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->unsignedSmallInteger('position')->unique();
            $table->timestampsTz(precision: 6);
        });

        Schema::create('role_ability', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->uuid('ability_id');
            $table->primary(['role_id', 'ability_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('ability_id')->references('id')->on('abilities')->cascadeOnDelete();
        });

        Schema::create('user_role', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        $now = now();
        DB::table('abilities')->insert(array_map(
            static fn (AbilityName $ability, int $position): array => [
                'id' => (string) Str::uuid(),
                'name' => $ability->value,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            AbilityName::cases(),
            array_keys(AbilityName::cases()),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_ability');
        Schema::dropIfExists('abilities');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('users');
    }
};
