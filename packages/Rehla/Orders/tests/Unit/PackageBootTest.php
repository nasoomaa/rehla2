<?php

declare(strict_types=1);

namespace Rehla\Orders\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Orders\Providers\OrdersServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(OrdersServiceProvider::class));
    }
}
