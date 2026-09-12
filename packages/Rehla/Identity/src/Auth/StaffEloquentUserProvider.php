<?php

declare(strict_types=1);

namespace Rehla\Identity\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Rehla\Identity\Enums\AccountStatus;

final class StaffEloquentUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null): Builder
    {
        return parent::newModelQuery($model)
            ->where('status', AccountStatus::Active->value)
            ->whereHas('staffProfile', static fn (Builder $query): Builder => $query->where('is_active', true));
    }
}
