<?php

declare(strict_types=1);

namespace Rehla\Notifications\Data;

use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class NotificationData
{
    public string $userId;

    public string $type;

    /** @var array{en: string, ar: string} */
    public array $title;

    /** @var array{en: string, ar: string} */
    public array $body;

    public string $targetLink;

    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $body
     */
    public function __construct(
        string $userId,
        string $type,
        array $title,
        array $body,
        string $targetLink,
    ) {
        OpaqueId::fromString($userId);
        if (preg_match('/^[a-z][a-z0-9_]*$/', $type) !== 1 || strlen($type) > 80) {
            throw new InvalidArgumentException('Invalid notification type.');
        }
        if (count($title) !== 2 || ! isset($title['en'], $title['ar'])
            || count($body) !== 2 || ! isset($body['en'], $body['ar'])) {
            throw new InvalidArgumentException('Notification content must contain English and Arabic values.');
        }
        foreach ([...array_values($title), ...array_values($body)] as $value) {
            if (trim($value) === '' || mb_strlen($value) > 1000) {
                throw new InvalidArgumentException('Invalid notification content.');
            }
        }
        if (! str_starts_with($targetLink, '/')
            || str_starts_with($targetLink, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $targetLink) === 1
            || strlen($targetLink) > 255) {
            throw new InvalidArgumentException('Invalid notification target link.');
        }

        $this->userId = $userId;
        $this->type = $type;
        $this->title = ['en' => $title['en'], 'ar' => $title['ar']];
        $this->body = ['en' => $body['en'], 'ar' => $body['ar']];
        $this->targetLink = $targetLink;
    }
}
