<?php

declare(strict_types=1);

namespace Rehla\Core\Errors;

enum ProblemCode: string
{
    case WalletInsufficientBalance = 'wallet.insufficient_balance';
    case ServiceUnavailable = 'service.unavailable';
    case ServicePriceChanged = 'service.price_changed';
    case FormVersionChanged = 'form.version_changed';
    case TravelerPassportConflict = 'traveler.passport_conflict';
    case TravelerNotFound = 'traveler.not_found';
    case TravelerInvalidDates = 'traveler.invalid_dates';
    case TopUpReferenceUsed = 'top_up.reference_used';
    case IdempotencyKeyReused = 'idempotency.key_reused';
    case OperationInProgress = 'operation.in_progress';
    case DocumentNotClean = 'document.not_clean';
    case DocumentUnsupportedType = 'document.unsupported_type';
    case DocumentFileTooLarge = 'document.file_too_large';
    case DocumentVerificationFailed = 'document.verification_failed';
    case DocumentInvalidAttachment = 'document.invalid_attachment';
    case ForbiddenResource = 'auth.forbidden_resource';
}
