<?php

declare(strict_types=1);

namespace Rehla\Travelers\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Travelers\Providers\TravelersServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(TravelersServiceProvider::class));
    }
}
