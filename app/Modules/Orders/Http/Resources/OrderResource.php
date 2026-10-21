<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'user_id' => $this->user_id, 'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email, 'status' => $this->status->value,
            'total_amount' => $this->total_amount, 'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
