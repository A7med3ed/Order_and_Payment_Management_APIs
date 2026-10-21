<?php

declare(strict_types=1);

namespace App\Modules\Shared\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Order and Payment Management API',
    description: 'Authenticate with a JWT bearer token. API errors use message/code and validation errors include errors. Limits are configurable; 429 includes Retry-After. Payment creation requires Idempotency-Key; replays count toward rate limits. Gateways are deterministic simulations.',
)]
#[OA\Server(url: '/', description: 'Current server')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Tag(name: 'Authentication', description: 'Register, log in, inspect the current user, and invalidate tokens.')]
#[OA\Tag(name: 'Orders', description: 'Owner-scoped order management and status transitions.')]
#[OA\Tag(name: 'Payments', description: 'Idempotent payment attempts using simulated gateways.')]
#[OA\Tag(name: 'Ledger', description: 'Read-only, append-only financial history.')]
#[OA\Tag(name: 'Documentation', description: 'Interactive API reference, enabled outside production by default.')]
final class ApiDocumentation
{
}
