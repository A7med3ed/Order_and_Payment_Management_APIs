<?php

declare(strict_types=1);

namespace App\Modules\Shared\DTOs;

final readonly class PaginationData
{
    public function __construct(public int $page = 1, public int $perPage = 15)
    {
    }
}
