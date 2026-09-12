<?php

declare(strict_types=1);

namespace Rehla\Audit\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AuditEntry extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'audit_entries';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'old_state' => 'array',
            'new_state' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
