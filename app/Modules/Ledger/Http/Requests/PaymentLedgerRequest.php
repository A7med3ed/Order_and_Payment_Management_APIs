<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Http\Requests;

use App\Modules\Ledger\DTOs\PaymentLedgerData;
use App\Modules\Shared\DTOs\PaginationData;
use Illuminate\Foundation\Http\FormRequest;

final class PaymentLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    public function toDto(): PaymentLedgerData
    {
        return new PaymentLedgerData(
            (int) $this->route('payment'),
            (int) $this->user()->getAuthIdentifier(),
            new PaginationData((int) $this->validated('page', 1), (int) $this->validated('per_page', 15))
        );
    }
}
