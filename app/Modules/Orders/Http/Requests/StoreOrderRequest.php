<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\DTOs\CreateOrderData;

final class StoreOrderRequest extends OrderWriteRequest
{
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            ...$this->itemRules(),
        ];
    }
    public function toDto(): CreateOrderData
    {
        return new CreateOrderData((int) $this->user()->getAuthIdentifier(), $this->validated('customer_name'), $this->validated('customer_email'), $this->itemData());
    }
}
