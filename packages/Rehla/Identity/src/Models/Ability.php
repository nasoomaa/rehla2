<?php

declare(strict_types=1);

namespace Rehla\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Ability extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $guarded = ['*'];
}
