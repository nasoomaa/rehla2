<?php

it('discovers every declared Rehla package provider', function (): void {
    $map = json_decode(
        file_get_contents(base_path('docs/architecture/rehla-package-map.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($map['schema_version'])->toBe(2)
        ->and($map['packages'])->toHaveCount(19);

    foreach (array_keys($map['packages']) as $package) {
        $provider = "Rehla\\{$package}\\Providers\\{$package}ServiceProvider";

        expect(class_exists($provider))->toBeTrue("Class {$provider} does not exist")
            ->and(app()->getProvider($provider))->not->toBeNull("Provider {$provider} is not registered");
    }
});
