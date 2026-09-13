<?php

declare(strict_types=1);

namespace Rehla\Notifications\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;
use Rehla\Notifications\Contracts\NotificationRecorder;
use Rehla\Notifications\Data\NotificationData;
use Rehla\Notifications\Data\NotificationSnapshot;

final readonly class CreateInAppNotification implements NotificationRecorder
{
    public function __construct(private Clock $clock) {}

    public function record(NotificationData $data): NotificationSnapshot
    {
        $id = OpaqueId::generate()->value();
        $createdAt = $this->clock->now()->setMicrosecond(0);
        DB::table('notifications')->insert([
            'id' => $id,
            'user_id' => $data->userId,
            'type' => $data->type,
            'payload' => json_encode([
                'title' => $data->title,
                'body' => $data->body,
                'target_link' => $data->targetLink,
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $createdAt,
        ]);

        return new NotificationSnapshot(
            $id,
            $data->type,
            $data->title,
            $data->body,
            $data->targetLink,
            null,
            $createdAt,
        );
    }
}
