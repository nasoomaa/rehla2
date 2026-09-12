<?php

declare(strict_types=1);

namespace Rehla\Catalog\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Catalog\Providers\CatalogServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(CatalogServiceProvider::class));
    }
}
