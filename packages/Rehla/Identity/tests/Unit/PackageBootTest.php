<?php

declare(strict_types=1);

namespace Rehla\Identity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Identity\Providers\IdentityServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(IdentityServiceProvider::class));
    }
}
