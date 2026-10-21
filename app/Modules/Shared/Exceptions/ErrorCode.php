<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case ValidationError = 'VALIDATION_ERROR';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case ServerError = 'SERVER_ERROR';
    case OrderHasPayments = 'ORDER_HAS_PAYMENTS';
    case OrderNotConfirmed = 'ORDER_NOT_CONFIRMED';
    case OrderAlreadyPaid = 'ORDER_ALREADY_PAID';
    case PaymentInProgress = 'PAYMENT_IN_PROGRESS';
    case InvalidStatusTransition = 'INVALID_STATUS_TRANSITION';
    case OrderNotEditable = 'ORDER_NOT_EDITABLE';
    case IdempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case IdempotencyInProgress = 'IDEMPOTENCY_IN_PROGRESS';
}
