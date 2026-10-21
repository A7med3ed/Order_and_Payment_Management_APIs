<?php

declare(strict_types=1);

namespace App\Modules\Shared\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['message', 'code'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Authentication is required or the token is invalid.'),
        new OA\Property(property: 'code', type: 'string', example: 'UNAUTHENTICATED'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ErrorResponse'),
        new OA\Schema(
            required: ['errors'],
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
                ),
            ],
            type: 'object',
        ),
    ],
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    required: ['current_page', 'last_page', 'per_page', 'total'],
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 1),
    ],
    type: 'object',
)]
final class CommonSchemas
{
}
