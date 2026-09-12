<?php

declare(strict_types=1);

namespace Rehla\Purchasing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Purchasing\Providers\PurchasingServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(PurchasingServiceProvider::class));
    }
}
