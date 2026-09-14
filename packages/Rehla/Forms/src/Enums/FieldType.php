<?php

declare(strict_types=1);

namespace Rehla\Forms\Enums;

enum FieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case Dropdown = 'dropdown';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case FileUpload = 'file_upload';
    case ImageUpload = 'image_upload';

    public function isChoice(): bool
    {
        return in_array($this, [self::Dropdown, self::Radio], true);
    }

    public function isDocument(): bool
    {
        return in_array($this, [self::FileUpload, self::ImageUpload], true);
    }
}
