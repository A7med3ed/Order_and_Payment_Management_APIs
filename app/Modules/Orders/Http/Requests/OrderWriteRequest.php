<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\DTOs\OrderItemData;
use Illuminate\Foundation\Http\FormRequest;

abstract class OrderWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function itemRules(): array
    {
        return [
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'items.*.price' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/D', 'max:13'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'An order must have at least one item.',
            'items.*.quantity.min' => 'Each item quantity must be at least one.',
            'items.*.price.string' => 'Prices must be decimal strings, for example "19.99".',
            'items.*.price.regex' => 'Prices must be non-negative decimal strings with at most two decimal places.',
        ];
    }

    /** @return list<OrderItemData> */
    protected function itemData(): array
    {
        return array_map(static fn (array $item): OrderItemData =>
            new OrderItemData($item['product_name'], (int) $item['quantity'], $item['price']), $this->validated('items'));
    }
}
