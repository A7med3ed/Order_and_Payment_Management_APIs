<?php

declare(strict_types=1);

namespace App\Modules\Payments\Docs;

use OpenApi\Attributes as OA;

#[OA\Parameter(parameter: 'PaymentId', name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))]
#[OA\Parameter(parameter: 'PaymentStatusFilter', name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'successful', 'failed']))]
#[OA\Parameter(parameter: 'PaymentMethodFilter', name: 'method', in: 'query', description: 'Historical gateway name; disabled methods may still be filtered.', schema: new OA\Schema(type: 'string'))]
final class PaymentParameters
{
}
