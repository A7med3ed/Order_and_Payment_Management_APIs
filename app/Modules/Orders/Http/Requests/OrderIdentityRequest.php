<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\DTOs\OrderIdentityData;
use Illuminate\Foundation\Http\FormRequest;

final class OrderIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    public function rules(): array
    {
        return [];
    }
    public function toDto(): OrderIdentityData
    {
        return new OrderIdentityData((int) $this->route('order'), (int) $this->user()->getAuthIdentifier());
    }
}
