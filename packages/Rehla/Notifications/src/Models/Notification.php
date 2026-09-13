<?php

declare(strict_types=1);

namespace Rehla\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
