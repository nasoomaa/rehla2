<?php

declare(strict_types=1);

use Tests\Architecture\Support\ArchitectureScanner;

function architectureJson(string $relativePath): array
{
    return json_decode(
        (string) file_get_contents(base_path($relativePath)),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

it('detects forbidden direct grouped and fully qualified package references', function (): void {
    $code = <<<'PHP'
    <?php
    namespace Rehla\Purchasing\Actions;

    use Rehla\Admin\Resources\OrderResource;
    use Rehla\Wallet\Models\{Wallet, LedgerEntry};
    use Rehla\Wallet\Contracts\DebitWallet;

    $model = new \Rehla\Orders\Models\Order();
    PHP;

    $references = ArchitectureScanner::references($code, 'SubmitOrder.php');
    $names = array_column($references, 'referenced');

    expect($names)->toContain(
        'Rehla\Admin\Resources\OrderResource',
        'Rehla\Wallet\Models\Wallet',
        'Rehla\Wallet\Models\LedgerEntry',
        'Rehla\Wallet\Contracts\DebitWallet',
        'Rehla\Orders\Models\Order',
    );

    $violations = ArchitectureScanner::dependencyViolations(
        'Purchasing',
        $references,
        ['Core', 'Wallet', 'Orders'],
    );

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('SubmitOrder.php', 'Purchasing', 'Rehla\Admin\Resources\OrderResource');
});

it('ignores comments strings and closure capture syntax', function (): void {
    $code = <<<'PHP'
    <?php
    namespace Rehla\Wallet\Actions;

    // Rehla\Admin\Resources\OrderResource
    $class = 'Rehla\\Admin\\Resources\\OrderResource';
    $callback = function () use ($class): string { return $class; };
    PHP;

    expect(ArchitectureScanner::references($code))->toHaveCount(1)
        ->and(ArchitectureScanner::references($code)[0]['referenced'])->toBe('Rehla\Wallet\Actions');
});

it('matches composer requirements to the declared dependency graph exactly', function (): void {
    $graph = architectureJson('docs/architecture/rehla-package-map.json')['packages'];
    $packageNames = array_combine(array_map('strtolower', array_keys($graph)), array_keys($graph));

    foreach ($graph as $consumer => $providers) {
        $manifest = architectureJson("packages/Rehla/{$consumer}/composer.json");
        $actual = [];

        foreach (array_keys($manifest['require']) as $requirement) {
            if (str_starts_with($requirement, 'rehla/')) {
                $actual[] = $packageNames[substr($requirement, strlen('rehla/'))] ?? $requirement;
            }
        }

        expect($actual)->toBe($providers, "{$consumer} Composer dependencies differ from the package map.");
    }
});

it('covers every dependency edge once with surfaces owned by its provider', function (): void {
    $graph = architectureJson('docs/architecture/rehla-package-map.json')['packages'];
    $contracts = architectureJson('docs/architecture/rehla-package-contract-map.json');
    $records = [];

    foreach ($contracts['dependencies'] as $record) {
        $key = "{$record['consumer']}->{$record['provider']}";
        expect($records)->not->toHaveKey($key);
        $records[$key] = $record;

        expect($record['surfaces'])->not->toBeEmpty();
        foreach ($record['surfaces'] as $surface) {
            expect($contracts['surface_groups'])->toHaveKey($surface)
                ->and($contracts['surface_groups'][$surface]['owner'])->toBe($record['provider']);
        }
    }

    $expected = [];
    foreach ($graph as $consumer => $providers) {
        foreach ($providers as $provider) {
            $expected[] = "{$consumer}->{$provider}";
        }
    }

    expect(array_keys($records))->toEqualCanonicalizing($expected);
});

it('keeps repository source references inside declared package edges', function (): void {
    $graph = architectureJson('docs/architecture/rehla-package-map.json')['packages'];
    $violations = [];

    foreach ($graph as $consumer => $providers) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            base_path("packages/Rehla/{$consumer}/src"),
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $references = ArchitectureScanner::references((string) file_get_contents($file->getPathname()), $file->getPathname());
            array_push($violations, ...ArchitectureScanner::dependencyViolations($consumer, $references, $providers));
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});
