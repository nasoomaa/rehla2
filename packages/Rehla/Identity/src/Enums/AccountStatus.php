<?php

declare(strict_types=1);

namespace Rehla\Identity\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
