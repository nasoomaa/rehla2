<?php

declare(strict_types=1);

namespace Rehla\Notifications\Infrastructure;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Rehla\Core\Time\Clock;
use Rehla\Identity\Contracts\RegistrationNotificationRecorder;
use Rehla\Notifications\Contracts\NotificationRecorder;
use Rehla\Notifications\Contracts\OutboxWriter;
use Rehla\Notifications\Data\NotificationData;
use Rehla\Notifications\Data\OutboxMessageData;

final readonly class IdentityRegistrationNotificationRecorder implements RegistrationNotificationRecorder
{
    public function __construct(
        private NotificationRecorder $notifications,
        private OutboxWriter $outbox,
        private Clock $clock,
    ) {}

    public function recordWelcome(string $accountId, string $locale, string $correlationId): void
    {
        $notification = new NotificationData(
            userId: $accountId,
            type: 'welcome',
            title: [
                'en' => $this->translation('welcome.title', 'en'),
                'ar' => $this->translation('welcome.title', 'ar'),
            ],
            body: [
                'en' => $this->translation('welcome.body', 'en'),
                'ar' => $this->translation('welcome.body', 'ar'),
            ],
            targetLink: '/account',
        );
        $this->notifications->record($notification);

        $channels = config('rehla-notifications.registration_channels', []);
        if (! is_array($channels)) {
            throw new InvalidArgumentException('Registration notification channels must be an array.');
        }
        foreach ($channels as $channel) {
            if (! is_string($channel) || ! in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
                throw new InvalidArgumentException('Unsupported registration notification channel.');
            }
            $this->outbox->append(new OutboxMessageData(
                eventName: 'identity.customer_registered',
                aggregateType: 'user',
                aggregateId: $accountId,
                payloadVersion: 1,
                payload: [
                    'recipient_id' => $accountId,
                    'preferred_locale' => $locale,
                    'channel' => $channel,
                    'correlation_id' => $correlationId,
                ],
                deduplicationKey: "identity_customer_registered:{$accountId}:{$channel}:v1",
                availableAt: $this->clock->now(),
            ));
        }
    }

    private function translation(string $key, string $locale): string
    {
        $translation = Lang::get("rehla-notifications::messages.{$key}", [], $locale);
        if (! is_string($translation)) {
            throw new InvalidArgumentException('Invalid notification translation.');
        }

        return $translation;
    }
}
