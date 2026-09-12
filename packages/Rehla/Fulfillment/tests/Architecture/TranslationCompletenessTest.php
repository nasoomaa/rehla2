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
