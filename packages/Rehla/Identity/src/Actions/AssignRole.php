<?php

declare(strict_types=1);

namespace Rehla\Identity\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Identity\Contracts\AuthorizesActor;
use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Enums\AbilityName;

final readonly class AssignRole
{
    public function __construct(
        private AuthorizesActor $authorization,
        private AuditWriter $audit,
    ) {}

    public function handle(ActorData $actor, string $accountId, string $roleName, string $correlationId): void
    {
        OpaqueId::fromString($accountId);
        OpaqueId::fromString($correlationId);

        DB::transaction(function () use ($actor, $accountId, $roleName, $correlationId): void {
            $lockedAccounts = DB::table('users')
                ->whereIn('id', collect([$actor->id, $accountId])->unique()->sort()->values()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            if ($lockedAccounts->count() !== count(array_unique([$actor->id, $accountId]))) {
                throw new InvalidArgumentException;
            }

            if (! $this->authorization->allows($actor, AbilityName::AccessManage)) {
                throw new AuthorizationException;
            }

            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! is_string($roleId)) {
                throw new InvalidArgumentException;
            }

            $inserted = DB::table('user_role')->insertOrIgnore([
                'user_id' => $accountId,
                'role_id' => $roleId,
            ]);

            if ($inserted === 0) {
                return;
            }

            $this->audit->append(new AppendAuditData(
                actorType: 'staff',
                actorId: $actor->id,
                action: 'identity.role_assigned',
                subjectType: 'user',
                subjectId: $accountId,
                metadata: ['role' => $roleName],
                oldState: null,
                newState: ['role_assigned' => true],
                reason: null,
                correlationId: $correlationId,
            ));
        });
    }
}
