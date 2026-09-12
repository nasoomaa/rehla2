<?php

function rehlaPackageMap(): array
{
    return json_decode(
        file_get_contents(base_path('docs/architecture/rehla-package-map.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    )['packages'];
}

function recursiveTranslationShape(array $messages): array
{
    $shape = [];
    foreach ($messages as $key => $value) {
        $shape[$key] = is_array($value) ? recursiveTranslationShape($value) : 'scalar';
    }
    ksort($shape);

    return $shape;
}

it('keeps package roots manifests providers and translations aligned', function (): void {
    foreach (rehlaPackageMap() as $package => $dependencies) {
        $root = base_path("packages/Rehla/{$package}");
        $entries = array_values(array_diff(scandir($root), ['.', '..']));
        sort($entries);

        expect($entries)->toBe(['README.md', 'composer.json', 'src', 'tests']);

        $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $expectedRequires = array_map(fn (string $dependency): string => 'rehla/'.strtolower($dependency), $dependencies);
        $actualRequires = array_values(array_filter(
            array_keys($manifest['require']),
            fn (string $name): bool => str_starts_with($name, 'rehla/'),
        ));

        expect($manifest['name'])->toBe('rehla/'.strtolower($package))
            ->and($manifest['autoload']['psr-4'])->toBe(["Rehla\\{$package}\\" => 'src/'])
            ->and($manifest['extra']['laravel']['providers'])->toBe(["Rehla\\{$package}\\Providers\\{$package}ServiceProvider"])
            ->and($actualRequires)->toBe($expectedRequires);

        $provider = file_get_contents($root."/src/Providers/{$package}ServiceProvider.php");
        expect($provider)->toContain("__DIR__.'/../resources/lang'")
            ->and($provider)->toContain("'rehla-".strtolower($package)."'");

        $english = require $root.'/src/resources/lang/en/messages.php';
        $arabic = require $root.'/src/resources/lang/ar/messages.php';
        expect(recursiveTranslationShape($english))->toBe(recursiveTranslationShape($arabic));
    }
});

it('does not hardcode visible copy in behavior and presentation definitions', function (): void {
    $violations = [];
    $sensitiveDirectories = ['Actions', 'Controllers', 'Jobs', 'Policies', 'Filament'];

    foreach (array_keys(rehlaPackageMap()) as $package) {
        foreach ($sensitiveDirectories as $directory) {
            $path = base_path("packages/Rehla/{$package}/src/{$directory}");
            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                    if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                        continue;
                    }
                    $value = trim($token[1], "'\"");
                    if (preg_match('/[A-Za-z]{2,}\s+[A-Za-z]{2,}/', $value) === 1) {
                        $violations[] = $file->getPathname().':'.$token[2].': '.$value;
                    }
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
