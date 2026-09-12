<?php

it('boots the Rehla host in testing mode', function (): void {
    expect(app()->environment())->toBe('testing')
        ->and(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.database'))->toBe('rehla_testing');
});
