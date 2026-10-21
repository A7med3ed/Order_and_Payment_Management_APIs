<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\DTOs\OrderFilterData;
use App\Modules\Shared\DTOs\PaginationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    public function rules(): array
    {
        return ['status' => ['sometimes', Rule::enum(OrderStatus::class)], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
    public function toDto(): OrderFilterData
    {
        return new OrderFilterData(
            (int) $this->user()->getAuthIdentifier(),
            $this->has('status') ? OrderStatus::from($this->validated('status')) : null,
            new PaginationData((int) $this->validated('page', 1), (int) $this->validated('per_page', 15))
        );
    }
}
