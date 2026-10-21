<?php

declare(strict_types=1);

namespace App\Modules\Payments\Docs;

use OpenApi\Attributes as OA;

final class PaymentEndpoints
{
    private const PAYMENT = ['id' => 1, 'order_id' => 1, 'user_id' => 1, 'status' => 'successful', 'method' => 'credit_card', 'amount' => '39.98', 'currency' => 'USD', 'transaction_id' => 'cc_example', 'failure_code' => null, 'created_at' => '2026-10-21T00:00:00Z', 'updated_at' => '2026-10-21T00:00:00Z'];
    private const PAGE = ['data' => [self::PAYMENT], 'links' => ['first' => '/api/payments?page=1', 'last' => '/api/payments?page=1', 'prev' => null, 'next' => null], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1]];
    #[OA\Post(
        path: '/api/orders/{order}/payments',
        operationId: 'processPayment',
        summary: 'Pay a confirmed order',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        description: 'Simulated charges: card ending 0002 declines, 0003 raises a provider error; PayPal fail@ declines, error@ raises a provider error. Successful and failed attempts return 201. Authentication runs before the per-user and per-order limits, followed by idempotency. Replays count toward both limits. An existing pending attempt is returned without another charge for the same key; a different key receives PAYMENT_IN_PROGRESS. Pending recovery requires operator reconciliation. The domain key/fingerprint remains effective after the 24-hour replay cache expires.',
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId'), new OA\Parameter(ref: '#/components/parameters/IdempotencyKey')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProcessPayment', examples: [
            new OA\Examples(example: 'CreditCard', value: ['method' => 'credit_card', 'payment_details' => ['card_number' => '4242424242424242', 'expiry_month' => 12, 'expiry_year' => 2030, 'cvv' => '123', 'card_holder' => 'Jane Doe']]),
            new OA\Examples(example: 'PayPal', value: ['method' => 'paypal', 'payment_details' => ['email' => 'payer@example.com']]),
            new OA\Examples(example: 'DeclinedCard', value: ['method' => 'credit_card', 'payment_details' => ['card_number' => '4000000000000002', 'expiry_month' => 12, 'expiry_year' => 2030, 'cvv' => '123', 'card_holder' => 'Jane Doe']]),
        ])),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Persisted payment attempt (including failed payments). Replay returns identical status/body and Idempotent-Replayed: true. No provider internals are exposed.',
                headers: [new OA\Header(header: 'Idempotent-Replayed', description: 'Present with value true on a cached replay.', schema: new OA\Schema(type: 'string', example: 'true'))],
                content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse', examples: [
                    new OA\Examples(example: 'SuccessfulPayment', value: ['data' => ['id' => 1, 'order_id' => 1, 'user_id' => 1, 'status' => 'successful', 'method' => 'credit_card', 'amount' => '39.98', 'currency' => 'USD', 'transaction_id' => 'cc_example', 'failure_code' => null, 'created_at' => '2026-10-21T00:00:00Z', 'updated_at' => '2026-10-21T00:00:00Z']]),
                    new OA\Examples(example: 'FailedPayment', value: ['data' => ['id' => 2, 'order_id' => 1, 'user_id' => 1, 'status' => 'failed', 'method' => 'credit_card', 'amount' => '39.98', 'currency' => 'USD', 'transaction_id' => null, 'failure_code' => 'CARD_DECLINED', 'created_at' => '2026-10-21T00:00:00Z', 'updated_at' => '2026-10-21T00:00:00Z']]),
                    new OA\Examples(example: 'IdempotentReplay', summary: 'Same response as the first successful request; replay header is true.', value: ['data' => ['id' => 1, 'order_id' => 1, 'user_id' => 1, 'status' => 'successful', 'method' => 'credit_card', 'amount' => '39.98', 'currency' => 'USD', 'transaction_id' => 'cc_example', 'failure_code' => null, 'created_at' => '2026-10-21T00:00:00Z', 'updated_at' => '2026-10-21T00:00:00Z']]),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Order or request conflict.', headers: [new OA\Header(header: 'Retry-After', description: 'One second for an in-progress request/payment.', schema: new OA\Schema(type: 'integer', example: 1))], content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', examples: [
                new OA\Examples(example: 'OrderNotConfirmed', value: ['message' => 'Only confirmed orders can be paid.', 'code' => 'ORDER_NOT_CONFIRMED']),
                new OA\Examples(example: 'OrderAlreadyPaid', value: ['message' => 'This order already has a successful payment.', 'code' => 'ORDER_ALREADY_PAID']),
                new OA\Examples(example: 'IdempotencyInProgress', value: ['message' => 'A request with this key is still processing.', 'code' => 'IDEMPOTENCY_IN_PROGRESS']),
                new OA\Examples(example: 'PaymentInProgress', value: ['message' => 'This order has a pending payment.', 'code' => 'PAYMENT_IN_PROGRESS']),
            ])),
            new OA\Response(response: 422, description: 'Invalid input or idempotency key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', examples: [
                new OA\Examples(example: 'ValidationError', value: ['message' => 'The given data was invalid.', 'code' => 'VALIDATION_ERROR', 'errors' => ['payment_details.cvv' => ['The CVV must contain 3 or 4 digits.']]]),
                new OA\Examples(example: 'KeyRequired', value: ['message' => 'The Idempotency-Key header is required.', 'code' => 'IDEMPOTENCY_KEY_REQUIRED']),
                new OA\Examples(example: 'KeyReused', value: ['message' => 'This key was already used for a different request.', 'code' => 'IDEMPOTENCY_KEY_REUSED']),
            ])),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/api/payments',
        operationId: 'listPayments',
        summary: 'List and filter your payments',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        description: 'Owner-scoped, newest first. Filters combine status, historical method, and order_id. No provider internals are exposed.',
        parameters: [new OA\Parameter(ref: '#/components/parameters/Page'), new OA\Parameter(ref: '#/components/parameters/PerPage'), new OA\Parameter(ref: '#/components/parameters/PaymentStatusFilter'), new OA\Parameter(ref: '#/components/parameters/PaymentMethodFilter'), new OA\Parameter(name: 'order_id', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))],
        responses: [new OA\Response(response: 200, description: 'Paginated payment attempts.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentPage', example: self::PAGE)),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'), new OA\Response(response: 422, ref: '#/components/responses/ValidationError'), new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')],
    )]
    public function index(): void
    {
    }

    #[OA\Get(
        path: '/api/orders/{order}/payments',
        operationId: 'listOrderPayments',
        summary: 'List an order payment attempts',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        description: 'Owner-scoped, newest first, including failed attempts. Another user receives 404.',
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId'), new OA\Parameter(ref: '#/components/parameters/Page'), new OA\Parameter(ref: '#/components/parameters/PerPage'), new OA\Parameter(ref: '#/components/parameters/PaymentStatusFilter'), new OA\Parameter(ref: '#/components/parameters/PaymentMethodFilter')],
        responses: [new OA\Response(response: 200, description: 'Paginated payment attempts.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentPage', example: self::PAGE)),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'), new OA\Response(response: 404, ref: '#/components/responses/NotFound'), new OA\Response(response: 422, ref: '#/components/responses/ValidationError'), new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')],
    )]
    public function forOrder(): void
    {
    }

    #[OA\Get(
        path: '/api/payments/{payment}',
        operationId: 'showPayment',
        summary: 'Get a payment attempt',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PaymentId')],
        responses: [new OA\Response(response: 200, description: 'Payment details without raw provider data.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse', example: ['data' => self::PAYMENT])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'), new OA\Response(response: 404, ref: '#/components/responses/NotFound'), new OA\Response(response: 422, ref: '#/components/responses/ValidationError'), new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests')],
    )]
    public function show(): void
    {
    }
}
