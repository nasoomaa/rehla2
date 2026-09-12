<?php

declare(strict_types=1);

namespace Rehla\Wallet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Wallet\Providers\WalletServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(WalletServiceProvider::class));
    }
}
