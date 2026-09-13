<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Notifications\Actions\MarkNotificationRead;
use Rehla\Notifications\Contracts\NotificationReader;
use Rehla\Notifications\Contracts\NotificationRecorder;
use Rehla\Notifications\Data\NotificationData;
use Rehla\Notifications\Exceptions\NotificationNotFound;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DB::statement('TRUNCATE TABLE notifications, outbox_messages, users, audit_entries RESTART IDENTITY CASCADE');
});

function createNotificationOwner(string $email): string
{
    $id = OpaqueId::generate()->value();
    DB::table('users')->insert([
        'id' => $id,
        'name' => 'Notification Owner',
        'email' => $email,
        'password' => 'not-used',
        'status' => 'active',
        'preferred_locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function notificationData(string $ownerId, string $type = 'order_confirmed'): NotificationData
{
    return new NotificationData(
        userId: $ownerId,
        type: $type,
        title: ['en' => 'Order received', 'ar' => 'تم استلام الطلب'],
        body: ['en' => 'Your order is being processed.', 'ar' => 'طلبك قيد المعالجة.'],
        targetLink: '/account/orders/order-1',
    );
}

it('lists only owned notifications with an unread count', function (): void {
    $ownerA = createNotificationOwner('notifications-a@example.test');
    $ownerB = createNotificationOwner('notifications-b@example.test');
    $owned = app(NotificationRecorder::class)->record(notificationData($ownerA));
    app(NotificationRecorder::class)->record(notificationData($ownerB));

    $page = app(NotificationReader::class)->listOwned($ownerA, 1, 20);

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0])->toEqual($owned)
        ->and($page->unreadCount)->toBe(1)
        ->and($page->page)->toBe(1)
        ->and($page->perPage)->toBe(20);
});

it('marks an owned notification read idempotently and hides foreign records', function (): void {
    $ownerA = createNotificationOwner('read-a@example.test');
    $ownerB = createNotificationOwner('read-b@example.test');
    $notification = app(NotificationRecorder::class)->record(notificationData($ownerA));

    expect(fn () => app(MarkNotificationRead::class)->handle($ownerB, $notification->id))
        ->toThrow(NotificationNotFound::class);

    $first = app(MarkNotificationRead::class)->handle($ownerA, $notification->id);
    $second = app(MarkNotificationRead::class)->handle($ownerA, $notification->id);

    expect($first->readAt)->not->toBeNull()
        ->and($second->readAt?->toISOString())->toBe($first->readAt?->toISOString())
        ->and(app(NotificationReader::class)->listOwned($ownerA, 1, 20, true)->items)->toBe([]);
});

it('requires complete bilingual customer visible content', function (): void {
    $ownerId = createNotificationOwner('invalid-notification@example.test');

    expect(fn () => new NotificationData(
        userId: $ownerId,
        type: 'order_confirmed',
        title: ['en' => 'Only English'],
        body: ['en' => 'Body', 'ar' => 'النص'],
        targetLink: '/account/orders/order-1',
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => new NotificationData(
        userId: $ownerId,
        type: 'order_confirmed',
        title: ['en' => 'Title', 'ar' => 'العنوان'],
        body: ['en' => 'Body', 'ar' => 'النص'],
        targetLink: '//external.example/path',
    ))->toThrow(InvalidArgumentException::class);
});
