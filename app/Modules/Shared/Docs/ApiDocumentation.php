<?php

declare(strict_types=1);

namespace App\Modules\Shared\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.1.0',
    title: 'Order and Payment Management API',
    description: 'Authenticate with a JWT bearer token. API errors use message/code and validation errors include errors. Limits are configurable; 429 includes Retry-After. Payment creation in a later sprint will require Idempotency-Key; replays count toward rate limits.',
)]
#[OA\Server(url: '/', description: 'Current server')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Tag(name: 'Authentication', description: 'Register, log in, inspect the current user, and invalidate tokens.')]
#[OA\Tag(name: 'Orders', description: 'Owner-scoped order management (introduced in Sprint 2).')]
#[OA\Tag(name: 'Payments', description: 'Idempotent payment attempts (introduced in Sprint 4).')]
#[OA\Tag(name: 'Ledger', description: 'Read-only financial history (introduced in Sprint 3).')]
#[OA\Tag(name: 'Documentation', description: 'Interactive API reference, enabled outside production by default.')]
final class ApiDocumentation
{
}
