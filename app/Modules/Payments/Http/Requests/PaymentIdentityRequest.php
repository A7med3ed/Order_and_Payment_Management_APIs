<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\DTOs\PaymentIdentityData;
use Illuminate\Foundation\Http\FormRequest;

final class PaymentIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }

    public function toDto(): PaymentIdentityData
    {
        return new PaymentIdentityData((int) $this->route('payment'), (int) $this->user()->getAuthIdentifier());
    }

}
