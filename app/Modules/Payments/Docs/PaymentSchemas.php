<?php

declare(strict_types=1);

namespace App\Modules\Payments\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Payment', type: 'object', required: ['id', 'order_id', 'user_id', 'status', 'method', 'amount', 'currency'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 1),
    new OA\Property(property: 'order_id', type: 'integer', example: 1),
    new OA\Property(property: 'user_id', type: 'integer', example: 1),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'successful', 'failed']),
    new OA\Property(property: 'method', type: 'string', description: 'Enabled gateway name; extensible through configuration.', example: 'credit_card'),
    new OA\Property(property: 'amount', type: 'string', example: '39.98'),
    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    new OA\Property(property: 'transaction_id', type: 'string', nullable: true),
    new OA\Property(property: 'failure_code', type: 'string', nullable: true),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
])]
#[OA\Schema(schema: 'PaymentResponse', type: 'object', required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])]
#[OA\Schema(schema: 'PaymentPage', type: 'object', required: ['data', 'links', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
])]
#[OA\Schema(schema: 'ProcessPayment', type: 'object', required: ['method', 'payment_details'], properties: [
    new OA\Property(property: 'method', type: 'string', description: 'credit_card, paypal, or an enabled extension.'),
    new OA\Property(property: 'payment_details', oneOf: [new OA\Schema(ref: '#/components/schemas/CardDetails'), new OA\Schema(ref: '#/components/schemas/PayPalDetails')], description: 'Validated dynamically by the selected gateway. Extensions define their own fields.'),
])]
#[OA\Schema(schema: 'CardDetails', type: 'object', additionalProperties: false, required: ['card_number', 'expiry_month', 'expiry_year', 'cvv', 'card_holder'], properties: [
    new OA\Property(property: 'card_number', type: 'string', pattern: '^[0-9]{13,19}$', example: '4242424242424242'),
    new OA\Property(property: 'expiry_month', type: 'integer', minimum: 1, maximum: 12, example: 12),
    new OA\Property(property: 'expiry_year', type: 'integer', minimum: 2000, maximum: 9999, example: 2030),
    new OA\Property(property: 'cvv', type: 'string', pattern: '^[0-9]{3,4}$', example: '123'),
    new OA\Property(property: 'card_holder', type: 'string', maxLength: 255, example: 'Jane Doe'),
])]
#[OA\Schema(schema: 'PayPalDetails', type: 'object', additionalProperties: false, required: ['email'], properties: [new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'payer@example.com')])]
final class PaymentSchemas
{
}
