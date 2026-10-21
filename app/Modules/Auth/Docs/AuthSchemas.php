<?php

declare(strict_types=1);

namespace App\Modules\Auth\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    required: ['id', 'name', 'email', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-21T00:00:00.000000Z'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserResponse',
    required: ['data'],
    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TokenResponse',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['access_token', 'token_type', 'expires_in', 'user'],
            properties: [
                new OA\Property(property: 'access_token', type: 'string', example: 'eyJ...example-jwt'),
                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
        new OA\Property(property: 'password', description: 'At least 8 characters including letters and numbers; at most 72 UTF-8 bytes (bcrypt limit).', type: 'string', format: 'password', minLength: 8, maxLength: 72, writeOnly: true),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', writeOnly: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string', format: 'password', writeOnly: true),
    ],
    type: 'object',
)]
final class AuthSchemas
{
}
