<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'order_id' => $this->orderId, 'user_id' => $this->userId,
            'status' => $this->status->value, 'method' => $this->method, 'amount' => $this->amount,
            'currency' => $this->currency, 'transaction_id' => $this->transactionId, 'failure_code' => $this->failureCode,
            'created_at' => $this->createdAt?->toISOString(), 'updated_at' => $this->updatedAt?->toISOString()];
    }
}
