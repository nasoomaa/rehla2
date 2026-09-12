<?php

declare(strict_types=1);

namespace Rehla\Documents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Documents\Providers\DocumentsServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(DocumentsServiceProvider::class));
    }
}
