<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssertsSafeTestingDatabase
{
    public static function check(): true
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($driver !== 'pgsql' || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(sprintf(
                'Tests require PostgreSQL and a database ending in _testing; received driver=%s database=%s.',
                $driver,
                $database,
            ));
        }

        return true;
    }
}
