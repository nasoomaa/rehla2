<?php

declare(strict_types=1);

namespace Rehla\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Role extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $guarded = ['*'];

    /** @return BelongsToMany<Ability, $this> */
    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'role_ability');
    }
}
