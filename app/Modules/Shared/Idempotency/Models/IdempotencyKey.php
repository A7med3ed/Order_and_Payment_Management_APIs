<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency\Models;

use App\Modules\Shared\Idempotency\Enums\IdempotencyStatus;
use Illuminate\Database\Eloquent\Model;

final class IdempotencyKey extends Model
{
    protected $guarded = [];
    protected $hidden = ['request_hash', 'response_body', 'response_headers'];
    protected function casts(): array
    {
        return ['status' => IdempotencyStatus::class, 'response_body' => 'json', 'response_headers' => 'array',
            'response_status' => 'integer', 'expires_at' => 'immutable_datetime', 'user_id' => 'integer'];
    }
}
