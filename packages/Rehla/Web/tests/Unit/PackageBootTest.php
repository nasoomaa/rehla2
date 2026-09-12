<?php

declare(strict_types=1);

namespace Rehla\Web\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Web\Providers\WebServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(WebServiceProvider::class));
    }
}
