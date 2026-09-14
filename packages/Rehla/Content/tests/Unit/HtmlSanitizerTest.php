<?php

declare(strict_types=1);

use Rehla\Content\Support\HtmlSanitizer;

it('keeps safe rich text and removes executable markup', function (): void {
    $html = '<h2>Title</h2><p class="lead" onmouseover="steal()">Text <strong>bold</strong> '
        .'<a href="https://rehla.test" target="_blank">safe</a> '
        .'<a href="javascript:steal()">unsafe</a></p><iframe src="https://evil.test"></iframe>';

    $clean = (new HtmlSanitizer)->sanitize($html);

    expect($clean)->toContain('<h2>Title</h2>')
        ->and($clean)->toContain('<strong>bold</strong>')
        ->and($clean)->toContain('href="https://rehla.test"')
        ->and($clean)->not->toContain('onmouseover', 'javascript:', '<iframe', 'class=');
});
