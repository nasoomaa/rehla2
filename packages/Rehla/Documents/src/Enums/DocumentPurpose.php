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

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::ApplicantPhoto => ['image/jpeg', 'image/png'],
            default => ['application/pdf', 'image/jpeg', 'image/png'],
        };
    }

    public function maximumBytes(string $mime): int
    {
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return 10_485_760;
        }

        return 20_971_520;
    }
}
