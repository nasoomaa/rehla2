<?php

use Illuminate\Support\LazyCollection;

const ACCEPTANCE_HEADER = [
    'acceptance_id',
    'source_requirement',
    'package',
    'interface',
    'db_invariant',
    'test_file',
    'test_name',
    'status',
    'evidence',
    'deferred_reason',
];

function acceptanceRecords(): LazyCollection
{
    $rows = LazyCollection::make(
        fn () => yield from array_map('str_getcsv', file(base_path('docs/requirements/rehla-phase-1-acceptance.csv'))),
    );

    expect($rows->first())->toBe(ACCEPTANCE_HEADER);

    return $rows->skip(1)->map(fn (array $row): array => array_combine(ACCEPTANCE_HEADER, $row));
}

it('maps every product section and mandatory atomic family', function (): void {
    $records = acceptanceRecords();
    $ids = $records->pluck('acceptance_id');

    foreach (range(1, 65) as $number) {
        $requirement = sprintf('R%02d', $number);
        expect($ids->contains(fn (string $id): bool => str_starts_with($id, $requirement.'.')))
            ->toBeTrue("Missing acceptance family {$requirement}");
    }

    $ranges = [
        'R08.' => 17,
        'R11.' => 6,
        'R17.' => 6,
        'R18.' => 10,
        'R33.' => 9,
        'R34.' => 11,
        'R40.' => 14,
        'R47.' => 30,
        'R50.' => 5,
        'R52.' => 13,
        'R62.' => 12,
    ];

    foreach ($ranges as $prefix => $last) {
        foreach (range(1, $last) as $number) {
            expect($ids)->toContain($prefix.sprintf('%02d', $number));
        }
    }

    foreach (['W' => 13, 'A' => 9] as $surface => $last) {
        foreach (range(1, $last) as $number) {
            expect($ids)->toContain('R63.'.$surface.sprintf('%02d', $number));
        }
    }

    $expectedInterfaces = [
        'R08' => [
            'FormFieldType::short_text', 'FormFieldType::long_text', 'FormFieldType::email',
            'FormFieldType::phone', 'FormFieldType::number', 'FormFieldType::date',
            'FormFieldType::dropdown', 'FormFieldType::radio', 'FormFieldType::checkbox',
            'FormFieldType::file_upload', 'FormFieldType::image_upload', 'FormField::label',
            'FormField::displayOrder', 'FormField::required', 'FormField::helperText',
            'FormField::options', 'FormField::validationRules',
        ],
        'R40' => array_map(
            fn (string $area): string => 'AdminArea::'.$area,
            ['Overview', 'Services', 'ApplicationForms', 'Customers', 'Travelers', 'WalletsLedger', 'BankAccounts', 'TopUpRequests', 'Orders', 'ServiceExecutions', 'Content', 'Notifications', 'RolesAbilities', 'AuditLog'],
        ),
        'R47' => array_map(
            fn (string $ability): string => 'Ability::'.$ability,
            ['admin.overview.view', 'services.view', 'services.manage', 'forms.view', 'forms.draft', 'forms.publish', 'customers.view', 'customers.view_sensitive', 'customers.manage_status', 'travelers.view', 'travelers.view_sensitive', 'wallets.view', 'banks.view', 'banks.manage', 'topups.view', 'topups.review', 'topups.settings.manage', 'orders.view', 'executions.view', 'executions.view_sensitive', 'executions.transition', 'executions.note', 'documents.view_sensitive', 'content.view', 'content.manage', 'notifications.view', 'notifications.replay', 'access.view', 'access.manage', 'audit.view'],
        ),
    ];

    foreach ($expectedInterfaces as $requirement => $interfaces) {
        $actual = $records->where('source_requirement', $requirement)
            ->pluck('interface')->sort()->values()->all();
        sort($interfaces);

        expect($actual)->toBe($interfaces);
    }
});

it('keeps every acceptance row unique complete and in a legal state', function (): void {
    $records = acceptanceRecords();
    $ids = $records->pluck('acceptance_id');

    expect($records)->toHaveCount(208)
        ->and($ids->unique())->toHaveCount(208);

    foreach ($records as $record) {
        expect($record['source_requirement'])->toMatch('/^R(?:0[1-9]|[1-5][0-9]|6[0-5])$/')
            ->and($record['acceptance_id'])->toStartWith($record['source_requirement'].'.')
            ->and($record['package'])->not->toBeEmpty()
            ->and($record['interface'])->not->toBeEmpty()
            ->and($record['db_invariant'])->not->toBeEmpty()
            ->and($record['test_file'])->not->toBeEmpty()
            ->and($record['test_name'])->not->toBeEmpty()
            ->and($record['status'])->toBeIn(['planned', 'red', 'green', 'verified', 'deferred']);

        if ($record['status'] === 'verified') {
            expect($record['evidence'])->not->toBeEmpty();
        }

        if ($record['status'] === 'deferred') {
            expect($record['deferred_reason'])->not->toBeEmpty();
        } else {
            expect($record['deferred_reason'])->toBeEmpty();
        }
    }
});

it('records the four accepted foundation decisions', function (): void {
    foreach (range(1, 4) as $number) {
        $path = base_path(sprintf('docs/adr/%04d-', $number));
        $matches = glob($path.'*.md');

        expect($matches)->toHaveCount(1)
            ->and(file_get_contents($matches[0]))->toContain("## Status\n\nAccepted");
    }
});
