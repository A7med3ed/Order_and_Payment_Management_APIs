<?php

declare(strict_types=1);

namespace App\Modules\Shared\Docs;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'Unauthenticated',
    description: 'Authentication is missing or the token is invalid or expired.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [new OA\Examples(example: 'Unauthenticated', value: ['message' => 'Authentication is required or the token is invalid.', 'code' => 'UNAUTHENTICATED'])],
    ),
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'The authenticated user is not allowed to perform this action.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [new OA\Examples(example: 'Forbidden', value: ['message' => 'You are not allowed to perform this action.', 'code' => 'FORBIDDEN'])],
    ),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'The resource does not exist or is not visible to this user.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [new OA\Examples(example: 'NotFound', value: ['message' => 'The requested resource was not found.', 'code' => 'NOT_FOUND'])],
    ),
)]
#[OA\Response(
    response: 'Conflict',
    description: 'The request conflicts with the resource state.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [new OA\Examples(example: 'Conflict', value: ['message' => 'The request conflicts with the current resource state.', 'code' => 'CONFLICT'])],
    ),
)]
#[OA\Response(
    response: 'ValidationError',
    description: 'Invalid input.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ValidationErrorResponse',
        examples: [new OA\Examples(example: 'ValidationError', value: ['message' => 'The given data was invalid.', 'code' => 'VALIDATION_ERROR', 'errors' => ['email' => ['The email field is required.']]])],
    ),
)]
#[OA\Response(
    response: 'TooManyRequests',
    description: 'Rate limit exceeded. Retry after the indicated delay.',
    headers: [
        new OA\Header(header: 'Retry-After', description: 'Seconds before retrying.', schema: new OA\Schema(type: 'integer', example: 60)),
        new OA\Header(header: 'X-RateLimit-Limit', description: 'Allowed requests in this window.', schema: new OA\Schema(type: 'integer', example: 5)),
        new OA\Header(header: 'X-RateLimit-Remaining', description: 'Remaining requests.', schema: new OA\Schema(type: 'integer', example: 0)),
    ],
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [new OA\Examples(example: 'TooManyRequests', value: ['message' => 'Too many requests. Please try again later.', 'code' => 'TOO_MANY_REQUESTS'])],
    ),
)]
#[OA\Response(
    response: 'ServerError',
    description: 'Unexpected error. Internal details are never exposed.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [new OA\Examples(example: 'ServerError', value: ['message' => 'An unexpected error occurred.', 'code' => 'SERVER_ERROR'])],
    ),
)]
final class CommonResponses
{
}
