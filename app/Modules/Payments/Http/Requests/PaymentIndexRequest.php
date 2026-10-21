<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\DTOs\PaymentFilterData;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Shared\DTOs\PaginationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Historical methods remain filterable when their gateway is later disabled.
        return ['status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'method' => ['sometimes', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/D'],
            'order_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    public function toDto(): PaymentFilterData
    {
        $orderId = $this->route('order') ?? $this->validated('order_id');
        return new PaymentFilterData(
            (int) $this->user()->getAuthIdentifier(),
            new PaginationData((int) $this->validated('page', 1), (int) $this->validated('per_page', 15)),
            $orderId === null ? null : (int) $orderId,
            $this->has('status') ? PaymentStatus::from($this->validated('status')) : null,
            $this->validated('method')
        );
    }
}
