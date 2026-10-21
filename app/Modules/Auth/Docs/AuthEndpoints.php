<?php

declare(strict_types=1);

namespace App\Modules\Auth\Docs;

use OpenApi\Attributes as OA;

final class AuthEndpoints
{
    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'register',
        summary: 'Register a user',
        description: 'Email is trimmed and normalized to lowercase. Returns the user; log in separately to obtain a JWT.',
        tags: ['Authentication'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/RegisterRequest',
                examples: [new OA\Examples(example: 'RegisterUser', value: [
                    'name' => 'Jane Doe', 'email' => 'jane@example.com',
                    'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
                ])],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created.', content: new OA\JsonContent(
                ref: '#/components/schemas/UserResponse',
                examples: [new OA\Examples(example: 'Registered', value: ['data' => ['id' => 1, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'created_at' => '2026-10-21T00:00:00.000000Z']])],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function register(): void
    {
    }

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'login',
        summary: 'Log in and obtain a JWT',
        description: 'Limited by normalized email and IP address. expires_in is seconds. Send access_token as Authorization: Bearer <token>.',
        tags: ['Authentication'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/LoginRequest',
                examples: [new OA\Examples(example: 'Login', value: ['email' => 'jane@example.com', 'password' => 'StrongPass123!'])],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated.', content: new OA\JsonContent(
                ref: '#/components/schemas/TokenResponse',
                examples: [new OA\Examples(example: 'Authenticated', value: ['data' => [
                    'access_token' => 'eyJ...example-jwt', 'token_type' => 'bearer', 'expires_in' => 3600,
                    'user' => ['id' => 1, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'created_at' => '2026-10-21T00:00:00.000000Z'],
                ]])],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function login(): void
    {
    }

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'currentUser',
        summary: 'Get the current user',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Current user.', content: new OA\JsonContent(
                ref: '#/components/schemas/UserResponse',
                examples: [new OA\Examples(example: 'CurrentUser', value: ['data' => ['id' => 1, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'created_at' => '2026-10-21T00:00:00.000000Z']])],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function me(): void
    {
    }

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'logout',
        summary: 'Invalidate the current JWT',
        description: 'The token is blacklisted until its expiry. Returns an empty response.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Token invalidated; no response body.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function logout(): void
    {
    }
}
