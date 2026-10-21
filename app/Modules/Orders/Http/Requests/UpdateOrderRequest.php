<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\DTOs\UpdateOrderData;
use Illuminate\Validation\Validator;

final class UpdateOrderRequest extends OrderWriteRequest
{
    public function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'items' => ['sometimes', 'required', 'array', 'min:1', 'max:1000'],
            ...$this->itemRules(),
        ];
    }
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_intersect(['customer_name', 'customer_email', 'items'], array_keys($this->all())) === []) {
                $validator->errors()->add('order', 'Provide customer_name, customer_email, or items to update.');
            }
        }];
    }
    public function toDto(): UpdateOrderData
    {
        return new UpdateOrderData(
            (int) $this->route('order'),
            (int) $this->user()->getAuthIdentifier(),
            $this->validated('customer_name'),
            $this->validated('customer_email'),
            $this->has('items') ? $this->itemData() : null
        );
    }
}
