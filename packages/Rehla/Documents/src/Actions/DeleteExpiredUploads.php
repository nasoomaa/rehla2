<?php

declare(strict_types=1);

namespace Rehla\Documents\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Enums\DocumentStatus;

final readonly class DeleteExpiredUploads
{
    public function __construct(
        private FilesystemManager $filesystems,
        private Clock $clock,
    ) {}

    public function handle(int $limit = 100): int
    {
        $purged = 0;
        for ($index = 0; $index < max(0, $limit); $index++) {
            $claim = $this->claimNext();
            if ($claim === null) {
                break;
            }

            $disk = $this->filesystems->disk($claim['disk']);
            if ($disk->exists($claim['storage_key']) && ! $disk->delete($claim['storage_key'])) {
                continue;
            }

            $purged += DB::transaction(function () use ($claim): int {
                $now = $this->clock->now();

                return DB::table('documents')
                    ->where('id', $claim['id'])
                    ->where('status', DocumentStatus::CleanupClaimed->value)
                    ->where('cleanup_claim_token', $claim['token'])
                    ->where('cleanup_lease_expires_at', '>=', $now)
                    ->update([
                        'status' => DocumentStatus::Purged->value,
                        'storage_key' => null,
                        'cleanup_claim_token' => null,
                        'cleanup_lease_expires_at' => null,
                        'purged_at' => $now,
                        'updated_at' => $now,
                    ]);
            });
        }

        return $purged;
    }

    /** @return array{id: string, disk: string, storage_key: string, token: string}|null */
    private function claimNext(): ?array
    {
        return DB::transaction(function (): ?array {
            $now = $this->clock->now();
            $row = DB::table('documents')
                ->where(function (Builder $query) use ($now): void {
                    $query
                        ->where(function (Builder $orphan) use ($now): void {
                            $orphan->whereIn('status', [
                                DocumentStatus::PendingScan->value,
                                DocumentStatus::Clean->value,
                            ])->where('created_at', '<=', $now->subDay());
                        })
                        ->orWhere(function (Builder $rejected) use ($now): void {
                            $rejected->where('status', DocumentStatus::Rejected->value)
                                ->where('scanned_at', '<=', $now->subDays(30));
                        })
                        ->orWhere(function (Builder $expiredClaim) use ($now): void {
                            $expiredClaim->where('status', DocumentStatus::CleanupClaimed->value)
                                ->where('cleanup_lease_expires_at', '<', $now);
                        });
                })
                ->orderBy('id')
                ->lock(implode(' ', ['for', 'update', 'skip', 'locked']))
                ->first();
            if ($row === null || ! is_string($row->storage_key)) {
                return null;
            }

            $token = OpaqueId::generate()->value();
            DB::table('documents')->where('id', $row->id)->update([
                'status' => DocumentStatus::CleanupClaimed->value,
                'cleanup_claim_token' => $token,
                'cleanup_lease_expires_at' => $now->addSeconds(
                    max(1, (int) config('rehla-documents.cleanup_lease_seconds', 600)),
                ),
                'updated_at' => $now,
            ]);

            return [
                'id' => (string) $row->id,
                'disk' => (string) $row->disk,
                'storage_key' => $row->storage_key,
                'token' => $token,
            ];
        });
    }
}
