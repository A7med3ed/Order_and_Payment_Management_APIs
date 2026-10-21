<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\DTOs\PaymentIdentityData;
use App\Modules\Shared\DTOs\PaginationData;
use Illuminate\Foundation\Http\FormRequest;

final class PaymentIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    public function toDto(): PaymentIdentityData
    {
        return new PaymentIdentityData((int) $this->route('payment'), (int) $this->user()->getAuthIdentifier());
    }

    public function pagination(): PaginationData
    {
        return new PaginationData((int) $this->validated('page', 1), (int) $this->validated('per_page', 15));
    }
}
