<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\DTOs\ChangeOrderStatusData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(OrderStatus::class)]];
    }
    public function toDto(): ChangeOrderStatusData
    {
        return new ChangeOrderStatusData((int) $this->route('order'), (int) $this->user()->getAuthIdentifier(), OrderStatus::from($this->validated('status')));
    }
}
