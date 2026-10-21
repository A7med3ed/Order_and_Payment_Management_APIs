<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\DTOs\ProcessPaymentData;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use App\Modules\Payments\Rules\SupportedPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

final class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(PaymentGatewayManager $gateways): array
    {
        $rules = ['method' => ['bail', 'required', 'string', 'max:64', new SupportedPaymentMethod($gateways)],
            'payment_details' => ['required', 'array']];
        $method = $this->input('method');
        if (is_string($method) && in_array($method, $gateways->supportedMethods(), true)) {
            $details = $gateways->resolve($method)->rules();
            // Reject unspecified keys so sensitive, unvalidated details never cross the boundary.
            $keys = array_unique(array_map(fn (string $key): string => explode('.', $key)[0], array_keys($details)));
            $rules['payment_details'] = ['required', 'array:'.implode(',', $keys)];
            foreach ($details as $key => $rule) {
                $rules['payment_details.'.$key] = $rule;
            }
        }
        return $rules;
    }

    public function messages(): array
    {
        return ['payment_details.required' => 'Provide the details required by the selected payment method.',
            'payment_details.array' => 'Payment details must contain only fields supported by the selected gateway.',
            'payment_details.card_number.regex' => 'The card number must contain 13 to 19 digits.',
            'payment_details.cvv.regex' => 'The CVV must contain 3 or 4 digits.'];
    }

    public function toDto(): ProcessPaymentData
    {
        return new ProcessPaymentData(
            (int) $this->route('order'),
            (int) $this->user()->getAuthIdentifier(),
            $this->validated('method'),
            $this->validated('payment_details'),
            $this->header('Idempotency-Key')
        );
    }
}
