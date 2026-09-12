<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsSafeTestingDatabase;

it('rejects a PostgreSQL database without the testing suffix', function (): void {
    DB::purge('pgsql');
    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql.database', 'rehla');

    expect(fn (): true => AssertsSafeTestingDatabase::check())
        ->toThrow(RuntimeException::class, '_testing');
});

it('rejects a non PostgreSQL driver even with a testing-looking name', function (): void {
    DB::purge('sqlite');
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', 'rehla_testing');

    expect(fn (): true => AssertsSafeTestingDatabase::check())
        ->toThrow(RuntimeException::class, 'PostgreSQL');
});

it('accepts the configured Rehla PostgreSQL testing database', function (): void {
    DB::purge('pgsql');
    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql.database', 'rehla_testing');

    expect(AssertsSafeTestingDatabase::check())->toBeTrue();
});
