<?php

declare(strict_types=1);

namespace Rehla\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Identity\Contracts\RegistrationNotificationRecorder;
use Rehla\Identity\Contracts\RegistrationWalletInitializer;
use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Data\RegisterCustomerData;
use Rehla\Identity\Data\UserData;
use Rehla\Identity\Enums\AccountStatus;

final readonly class RegisterCustomer
{
    public function __construct(
        private RegistrationWalletInitializer $wallets,
        private RegistrationNotificationRecorder $notifications,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(RegisterCustomerData $data): UserData
    {
        return DB::transaction(function () use ($data): UserData {
            $accountId = OpaqueId::generate()->value();
            $correlationId = OpaqueId::generate()->value();
            $now = $this->clock->now();

            DB::table('users')->insert([
                'id' => $accountId,
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'status' => AccountStatus::Active->value,
                'preferred_locale' => $data->preferredLocale,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AppendAuditData(
                actorType: 'customer',
                actorId: $accountId,
                action: 'identity.customer_registered',
                subjectType: 'user',
                subjectId: $accountId,
                metadata: ['preferred_locale' => $data->preferredLocale],
                oldState: null,
                newState: ['status' => AccountStatus::Active->value],
                reason: null,
                correlationId: $correlationId,
            ));

            $this->wallets->initialize($accountId);
            $this->notifications->recordWelcome($accountId, $data->preferredLocale, $correlationId);

            return new UserData(
                id: $accountId,
                name: $data->name,
                email: $data->email,
                status: AccountStatus::Active,
                preferredLocale: $data->preferredLocale,
                actor: new ActorData($accountId, 'customer'),
            );
        });
    }
}
