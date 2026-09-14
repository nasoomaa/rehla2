<?php

declare(strict_types=1);

namespace Rehla\Core\Errors;

enum ProblemCode: string
{
    case WalletInsufficientBalance = 'wallet.insufficient_balance';
    case ServiceUnavailable = 'service.unavailable';
    case ServiceNotFound = 'service.not_found';
    case ServicePriceChanged = 'service.price_changed';
    case ServiceFulfillmentPolicyMissing = 'service.fulfillment_policy_missing';
    case FulfillmentPolicyImmutable = 'fulfillment.policy_immutable';
    case FormVersionOutdated = 'form.version_outdated';
    case FormValidationFailed = 'form.validation_failed';
    case FormSchemaIntegrityFailed = 'form.schema_integrity_failed';
    case FormNotFound = 'form.not_found';
    case FormDraftNotFound = 'form.draft_not_found';
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
