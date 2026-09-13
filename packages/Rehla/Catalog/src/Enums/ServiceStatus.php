<?php

declare(strict_types=1);

namespace Rehla\Catalog\Enums;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
}
