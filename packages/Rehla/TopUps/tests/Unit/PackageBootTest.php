<?php

declare(strict_types=1);

namespace Rehla\TopUps\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\TopUps\Providers\TopUpsServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(TopUpsServiceProvider::class));
    }
}
