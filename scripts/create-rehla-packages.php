<?php

declare(strict_types=1);

const EXPECTED_REHLA_PACKAGES = [
    'Core', 'Audit', 'Identity', 'Catalog', 'Forms', 'Travelers', 'Documents',
    'Wallet', 'Notifications', 'TopUps', 'Orders', 'Purchasing', 'Fulfillment',
    'Content', 'Integrations', 'Reporting', 'Web', 'Api', 'Admin',
];

function readJson(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
}

function writeJson(string $path, array $data): void
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($path, $encoded."\n");
}

function writeIfMissing(string $path, string $contents): void
{
    if (! is_file($path)) {
        file_put_contents($path, $contents);
    }
}

function assertAcyclic(array $graph): void
{
    $state = array_fill_keys(array_keys($graph), 0);

    $visit = function (string $package) use (&$visit, &$state, $graph): void {
        if ($state[$package] === 1) {
            throw new RuntimeException("Dependency cycle reaches {$package}");
        }

        if ($state[$package] === 2) {
            return;
        }

        $state[$package] = 1;
        foreach ($graph[$package] as $dependency) {
            $visit($dependency);
        }
        $state[$package] = 2;
    };

    foreach (array_keys($graph) as $package) {
        $visit($package);
    }
}

function validateMaps(array $packageMap, array $contractMap, array $tableMap): void
{
    if (($packageMap['schema_version'] ?? null) !== 2) {
        throw new RuntimeException('Package map schema_version must be 2.');
    }

    if (array_keys($packageMap['packages'] ?? []) !== EXPECTED_REHLA_PACKAGES) {
        throw new RuntimeException('Package map must contain the exact ordered 19-package set.');
    }

    $edges = [];
    foreach ($packageMap['packages'] as $consumer => $dependencies) {
        if (count($dependencies) !== count(array_unique($dependencies))) {
            throw new RuntimeException("{$consumer} has duplicate dependencies.");
        }

        foreach ($dependencies as $provider) {
            if (! array_key_exists($provider, $packageMap['packages'])) {
                throw new RuntimeException("{$consumer} depends on unknown package {$provider}.");
            }
            $edges[] = "{$consumer}|{$provider}";
        }
    }

    if (count($edges) !== 98) {
        throw new RuntimeException('Package map must contain exactly 98 direct edges.');
    }

    assertAcyclic($packageMap['packages']);

    $contractEdges = array_map(
        fn (array $edge): string => $edge['consumer'].'|'.$edge['provider'],
        $contractMap['dependencies'] ?? [],
    );
    sort($edges);
    sort($contractEdges);
    if ($edges !== $contractEdges || count($contractEdges) !== count(array_unique($contractEdges))) {
        throw new RuntimeException('Contract dependency edges must equal the package map exactly.');
    }

    foreach ($tableMap['tables'] ?? [] as $table => $ownership) {
        $owner = $ownership['owner'] ?? null;
        if (! is_string($owner) || ! array_key_exists($owner, $packageMap['packages'])) {
            throw new RuntimeException("Table {$table} has an unknown owner.");
        }
        if (($ownership['writers'] ?? []) !== [$owner]) {
            throw new RuntimeException("Table {$table} must have only its owner as writer.");
        }
    }
}

function ownedTables(array $tableMap, string $package): array
{
    return array_keys(array_filter(
        $tableMap['tables'],
        fn (array $ownership): bool => $ownership['owner'] === $package,
    ));
}

function ownedSurfaces(array $contractMap, string $package): array
{
    $surfaces = [];
    foreach ($contractMap['surface_groups'] as $group => $definition) {
        if ($definition['owner'] === $package) {
            $surfaces[$group] = $definition['namespaces'];
        }
    }

    return $surfaces;
}

function readmeFor(
    string $package,
    array $dependencies,
    array $tables,
    array $surfaces,
): string {
    $dependencyLines = $dependencies === []
        ? '- No Rehla package dependencies.'
        : implode("\n", array_map(fn (string $dependency): string => "- `Rehla\\{$dependency}`", $dependencies));
    $tableLines = $tables === []
        ? '- No owned business tables.'
        : implode("\n", array_map(fn (string $table): string => "- `{$table}`", $tables));
    $surfaceLines = [];
    foreach ($surfaces as $group => $namespaces) {
        $surfaceLines[] = '- `'.$group.'`: `'.implode('`, `', $namespaces).'`';
    }
    $surfaceText = $surfaceLines === [] ? '- No public surface is declared yet.' : implode("\n", $surfaceLines);
    $namespace = 'rehla-'.strtolower($package);

    return <<<MARKDOWN
# Rehla {$package} Package

## Responsibility

This package owns the {$package} boundary declared by the Rehla architecture maps. It does not own another package's models, tables, authorization decisions, or external adapters.

## Rehla dependencies

{$dependencyLines}

## Owned tables

{$tableLines}

Only this package may create migrations for or write its owned tables. Consumers use declared commands or contracts and never receive mutable models.

## Declared public surfaces

{$surfaceText}

These are contract-map declarations for later owner tasks; the Foundation scaffold does not implement domain behavior prematurely.

## Runtime contract

- Authorization denies by default and is enforced by the owning action or query.
- Transaction participation uses the caller's connection when the contract declares it; this package never commits an outer transaction.
- External I/O does not run inside a business transaction. Required delivery is recorded through the owner Outbox contract after the relevant plan task exists.
- Public error identities use stable lowercase dot notation. Internal exceptions and messages are not public identities.
- Recovery disables the affected path or applies a forward-only correction after immutable records exist.

## Localization

Translations load from `src/resources/lang` under namespace `{$namespace}`. English and Arabic files must keep identical recursive keys and value shapes.

## Verification

```bash
php artisan test packages/Rehla/{$package}/tests
```

The root Architecture suite verifies dependencies, model boundaries, provider loading, translations, and table ownership.
MARKDOWN;
}

$root = dirname(__DIR__);
$packageMap = readJson($root.'/docs/architecture/rehla-package-map.json');
$contractMap = readJson($root.'/docs/architecture/rehla-package-contract-map.json');
$tableMap = readJson($root.'/docs/architecture/table-ownership.json');
validateMaps($packageMap, $contractMap, $tableMap);

$packagesRoot = $root.'/packages/Rehla';
if (! is_dir($packagesRoot) && ! mkdir($packagesRoot, 0755, true) && ! is_dir($packagesRoot)) {
    throw new RuntimeException("Cannot create {$packagesRoot}");
}

$providerTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace Rehla\{{PACKAGE}}\Providers;

use Illuminate\Support\ServiceProvider;

final class {{PACKAGE}}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', '{{NAMESPACE}}');
    }
}
PHP;

$bootTestTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace Rehla\{{PACKAGE}}\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\{{PACKAGE}}\Providers\{{PACKAGE}}ServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists({{PACKAGE}}ServiceProvider::class));
    }
}
PHP;

$translationTestTemplate = <<<'PHP'
<?php

declare(strict_types=1);

it('keeps English and Arabic translation shapes identical', function (): void {
    $translationShape = function (array $messages) use (&$translationShape): array {
        $shape = [];
        foreach ($messages as $key => $value) {
            $shape[$key] = is_array($value) ? $translationShape($value) : 'scalar';
        }
        ksort($shape);

        return $shape;
    };
    $english = require dirname(__DIR__, 2).'/src/resources/lang/en/messages.php';
    $arabic = require dirname(__DIR__, 2).'/src/resources/lang/ar/messages.php';

    expect($english)->toBeArray()
        ->and($arabic)->toBeArray()
        ->and($translationShape($english))->toBe($translationShape($arabic));
});
PHP;

foreach ($packageMap['packages'] as $package => $dependencies) {
    $packageRoot = $packagesRoot.'/'.$package;
    foreach (['src/Providers', 'src/resources/lang/en', 'src/resources/lang/ar', 'tests/Unit', 'tests/Architecture'] as $directory) {
        $path = $packageRoot.'/'.$directory;
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Cannot create {$path}");
        }
    }

    $requires = ['php' => '^8.5', 'illuminate/support' => '^13.0'];
    foreach ($dependencies as $dependency) {
        $requires['rehla/'.strtolower($dependency)] = '@dev';
    }
    $manifest = [
        'name' => 'rehla/'.strtolower($package),
        'description' => "Rehla {$package} package",
        'type' => 'library',
        'license' => 'proprietary',
        'require' => $requires,
        'autoload' => ['psr-4' => ["Rehla\\{$package}\\" => 'src/']],
        'autoload-dev' => ['psr-4' => ["Rehla\\{$package}\\Tests\\" => 'tests/']],
        'extra' => ['laravel' => ['providers' => ["Rehla\\{$package}\\Providers\\{$package}ServiceProvider"]]],
    ];
    writeJson($packageRoot.'/composer.json', $manifest);

    $replacements = [
        '{{PACKAGE}}' => $package,
        '{{NAMESPACE}}' => 'rehla-'.strtolower($package),
    ];
    writeIfMissing(
        $packageRoot.'/src/Providers/'.$package.'ServiceProvider.php',
        strtr($providerTemplate, $replacements)."\n",
    );
    writeIfMissing($packageRoot.'/src/resources/lang/en/messages.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
    writeIfMissing($packageRoot.'/src/resources/lang/ar/messages.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
    writeIfMissing($packageRoot.'/README.md', readmeFor(
        $package,
        $dependencies,
        ownedTables($tableMap, $package),
        ownedSurfaces($contractMap, $package),
    )."\n");
    writeIfMissing(
        $packageRoot.'/tests/Unit/PackageBootTest.php',
        strtr($bootTestTemplate, $replacements)."\n",
    );
    writeIfMissing(
        $packageRoot.'/tests/Architecture/TranslationCompletenessTest.php',
        $translationTestTemplate."\n",
    );
}

$rootComposer = readJson($root.'/composer.json');
$rootComposer['repositories'] = [[
    'type' => 'path',
    'url' => 'packages/Rehla/*',
    'options' => ['symlink' => true],
]];
foreach (['web', 'api', 'admin'] as $presentationPackage) {
    $rootComposer['require']['rehla/'.$presentationPackage] = '@dev';
}
$rootComposer['minimum-stability'] = 'dev';
$rootComposer['prefer-stable'] = true;
writeJson($root.'/composer.json', $rootComposer);

fwrite(STDOUT, "Created or verified 19 Rehla package scaffolds and 98 dependency edges.\n");
