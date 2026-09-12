<?php

declare(strict_types=1);

it('serves the framework health endpoint', function (): void {
    $this->get('/up')->assertOk();
});
