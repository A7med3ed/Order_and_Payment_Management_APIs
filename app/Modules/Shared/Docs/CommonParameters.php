<?php

declare(strict_types=1);

namespace App\Modules\Shared\Docs;

use OpenApi\Attributes as OA;

#[OA\Parameter(parameter: 'Page', name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
#[OA\Parameter(parameter: 'PerPage', name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1))]
#[OA\Parameter(
    parameter: 'IdempotencyKey',
    name: 'Idempotency-Key',
    in: 'header',
    required: true,
    description: 'Client-generated key, preferably a UUID, 8–255 characters. Completed identical requests replay status/body with Idempotent-Replayed: true; in-progress requests return 409 with Retry-After: 1; mismatches return 422. Default TTL 24 hours. Validation and server errors release the key.',
    schema: new OA\Schema(type: 'string', minLength: 8, maxLength: 255),
)]
final class CommonParameters
{
}
