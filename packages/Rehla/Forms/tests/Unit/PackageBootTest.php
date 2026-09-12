<?php

declare(strict_types=1);

namespace Rehla\Forms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Forms\Providers\FormsServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(FormsServiceProvider::class));
    }
}
