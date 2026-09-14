<?php

declare(strict_types=1);

namespace Rehla\Content\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
