<?php

declare(strict_types=1);

namespace Rehla\Api\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Api\Providers\ApiServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(ApiServiceProvider::class));
    }
}
