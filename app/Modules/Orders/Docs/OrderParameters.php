<?php

declare(strict_types=1);

namespace App\Modules\Orders\Docs;

use OpenApi\Attributes as OA;

#[OA\Parameter(parameter: 'OrderId', name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))]
final class OrderParameters
{
}
