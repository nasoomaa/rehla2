<?php

declare(strict_types=1);

use Tests\Support\PostgresConnections;

it('opens two independent PostgreSQL 18 sessions', function (): void {
    [$first, $second] = PostgresConnections::independentPair();

    try {
        $version = (int) $first->selectOne('select current_setting(\'server_version_num\')::int as version')->version;

        expect($first->getDriverName())->toBe('pgsql')
            ->and($second->getDriverName())->toBe('pgsql')
            ->and(intdiv($version, 10_000))->toBe(18)
            ->and($first->selectOne('select pg_backend_pid() as pid')->pid)
            ->not->toBe($second->selectOne('select pg_backend_pid() as pid')->pid);
    } finally {
        PostgresConnections::disconnectPair();
    }
});
