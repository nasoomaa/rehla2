<?php

declare(strict_types=1);

namespace Rehla\Documents\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Documents\Data\BeginUploadData;

final readonly class BeginUpload
{
    public function __construct(private Clock $clock) {}

    public function handle(BeginUploadData $data): string
    {
        $id = OpaqueId::generate()->value();
        $now = $this->clock->now();

        DB::table('upload_sessions')->insert([
            'id' => $id,
            'owner_id' => $data->ownerId,
            'purpose' => $data->purpose->value,
            'expires_at' => $now->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}
