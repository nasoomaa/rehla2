<?php

declare(strict_types=1);

use Tests\Architecture\Support\ArchitectureScanner;

it('extracts schema create calls with PHP tokens and ignores comments and strings', function (): void {
    $code = <<<'PHP'
    <?php
    // Schema::create('ignored_comment');
    $example = "Schema::create('ignored_string')";
    Schema::create('wallets', function ($table): void {});
    \Illuminate\Support\Facades\Schema::create("wallet_ledger_entries", function ($table): void {});
    PHP;

    expect(ArchitectureScanner::createdTables($code))->toBe(['wallets', 'wallet_ledger_entries']);
});

it('rejects unknown tables wrong owners duplicate creators and invalid writers', function (): void {
    $tables = [
        'wallets' => ['owner' => 'Wallet', 'writers' => ['Wallet'], 'reporting_readers' => []],
        'orders' => ['owner' => 'Orders', 'writers' => ['Orders', 'Purchasing'], 'reporting_readers' => []],
    ];
    $migrations = [
        'Orders' => [
            ['file' => 'create_wallets.php', 'code' => "<?php Schema::create('wallets', fn () => null);"],
            ['file' => 'create_unknown.php', 'code' => "<?php Schema::create('mystery', fn () => null);"],
        ],
        'Wallet' => [
            ['file' => 'create_wallets_again.php', 'code' => "<?php Schema::create('wallets', fn () => null);"],
        ],
    ];

    $violations = ArchitectureScanner::migrationViolations($migrations, $tables);
    $message = implode(PHP_EOL, $violations);

    expect($message)->toContain(
        'mystery',
        'not registered',
        'wallets',
        'owned by Wallet',
        'created more than once',
        'orders writers must equal [Orders]',
    );
});

it('validates the ownership registry and all package migrations', function (): void {
    $tables = architectureJson('docs/architecture/table-ownership.json')['tables'];
    $graph = architectureJson('docs/architecture/rehla-package-map.json')['packages'];
    $migrations = [];

    foreach (array_keys($graph) as $package) {
        $directory = base_path("packages/Rehla/{$package}/src/database/migrations");
        $migrations[$package] = [];

        if (! is_dir($directory)) {
            continue;
        }

        foreach (glob("{$directory}/*.php") ?: [] as $file) {
            $migrations[$package][] = ['file' => $file, 'code' => (string) file_get_contents($file)];
        }
    }

    expect(ArchitectureScanner::migrationViolations($migrations, $tables))
        ->toBeEmpty();
});
