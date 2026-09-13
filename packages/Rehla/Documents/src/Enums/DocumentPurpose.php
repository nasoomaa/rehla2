<?php

declare(strict_types=1);

namespace Rehla\Documents\Enums;

enum DocumentPurpose: string
{
    case BankReceipt = 'bank_receipt';
    case PassportScan = 'passport_scan';
    case IdentityDocument = 'identity_document';
    case ApplicantPhoto = 'applicant_photo';
    case SupportingDocument = 'supporting_document';
    case IssuedDocument = 'issued_document';
    case ServiceMedia = 'service_media';
    case BankLogo = 'bank_logo';

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::ApplicantPhoto, self::ServiceMedia, self::BankLogo => ['image/jpeg', 'image/png'],
            default => ['application/pdf', 'image/jpeg', 'image/png'],
        };
    }

    public function maximumBytes(string $mime): int
    {
        if ($this->isPublic()) {
            return 5_242_880;
        }

        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return 10_485_760;
        }

        return 20_971_520;
    }

    public function isPublic(): bool
    {
        return $this === self::ServiceMedia || $this === self::BankLogo;
    }
}
