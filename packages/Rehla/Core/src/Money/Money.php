<?php

declare(strict_types=1);

namespace Rehla\Core\Money;

use InvalidArgumentException;
use OverflowException;

final readonly class Money
{
    private function __construct(
        private int $minor,
    ) {}

    public static function sdg(int $minor): self
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money minor units cannot be negative.');
        }

        return new self($minor);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return 'SDG';
    }

    public function add(self $other): self
    {
        if ($this->minor > PHP_INT_MAX - $other->minor) {
            throw new OverflowException('Money addition exceeds the supported integer range.');
        }

        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        if ($other->minor > $this->minor) {
            throw new InvalidArgumentException('Money subtraction cannot produce a negative value.');
        }

        return new self($this->minor - $other->minor);
    }

    public function isLessThan(self $other): bool
    {
        return $this->minor < $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }
}
