<?php

declare(strict_types=1);

use Tests\Architecture\Support\ArchitectureScanner;

it('rejects cross package models through direct grouped and fully qualified syntax', function (): void {
    $code = <<<'PHP'
    <?php
    namespace Rehla\Purchasing\Actions;

    use Rehla\Wallet\Models\{Wallet, LedgerEntry};
    use Rehla\Wallet\Contracts\DebitWallet;

    $order = new \Rehla\Orders\Models\Order();
    PHP;

    $violations = ArchitectureScanner::modelViolations(
        'Purchasing',
        ArchitectureScanner::references($code, 'Purchase.php'),
    );

    expect($violations)->toHaveCount(3)
        ->and(implode(PHP_EOL, $violations))->toContain(
            'Rehla\Wallet\Models\Wallet',
            'Rehla\Wallet\Models\LedgerEntry',
            'Rehla\Orders\Models\Order',
        )->not->toContain('DebitWallet');
});

it('allows models owned by the current package', function (): void {
    $code = '<?php $wallet = new \\Rehla\\Wallet\\Models\\Wallet();';

    expect(ArchitectureScanner::modelViolations(
        'Wallet',
        ArchitectureScanner::references($code),
    ))->toBeEmpty();
});

it('keeps repository models inside their owning package', function (): void {
    $graph = architectureJson('docs/architecture/rehla-package-map.json')['packages'];
    $violations = [];

    foreach (array_keys($graph) as $consumer) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            base_path("packages/Rehla/{$consumer}/src"),
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $references = ArchitectureScanner::references((string) file_get_contents($file->getPathname()), $file->getPathname());
            array_push($violations, ...ArchitectureScanner::modelViolations($consumer, $references));
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});
