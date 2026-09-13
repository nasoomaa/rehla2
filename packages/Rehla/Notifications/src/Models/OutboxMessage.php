<?php

declare(strict_types=1);

namespace Rehla\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OutboxMessage extends Model
{
    use HasUuids;

    protected $table = 'outbox_messages';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'dead_lettered_at' => 'immutable_datetime',
        ];
    }
}
