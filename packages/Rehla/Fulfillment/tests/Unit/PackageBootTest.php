<?php

declare(strict_types=1);

namespace Rehla\Fulfillment\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Fulfillment\Providers\FulfillmentServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(FulfillmentServiceProvider::class));
    }
}
