<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PostgresConnections
{
    /** @var array{string, string} */
    private const array CONNECTION_NAMES = ['pgsql_concurrency_a', 'pgsql_concurrency_b'];

    /** @return array{Connection, Connection} */
    public static function independentPair(): array
    {
        AssertsSafeTestingDatabase::check();

        $configuration = config('database.connections.pgsql');
        if (! is_array($configuration)) {
            throw new RuntimeException('The PostgreSQL testing connection is not configured.');
        }

        $connections = [];
        foreach (self::CONNECTION_NAMES as $name) {
            DB::purge($name);
            config()->set("database.connections.{$name}", $configuration);
            $connections[] = DB::connection($name);
        }

        return [$connections[0], $connections[1]];
    }

    public static function disconnectPair(): void
    {
        foreach (self::CONNECTION_NAMES as $name) {
            DB::purge($name);
        }
    }
}
