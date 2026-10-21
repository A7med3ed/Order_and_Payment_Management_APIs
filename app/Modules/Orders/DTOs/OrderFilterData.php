<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Shared\DTOs\PaginationData;

final readonly class OrderFilterData
{
    public function __construct(public int $userId, public ?OrderStatus $status, public PaginationData $pagination)
    {
    }
}
