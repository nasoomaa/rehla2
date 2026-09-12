<?php

declare(strict_types=1);

namespace Rehla\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Core\Providers\CoreServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(CoreServiceProvider::class));
    }
}
