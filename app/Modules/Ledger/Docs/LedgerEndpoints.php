<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Docs;

use OpenApi\Attributes as OA;

final class LedgerEndpoints
{
    #[OA\Get(
        path: '/api/orders/{order}/ledger',
        operationId: 'orderLedger',
        summary: 'Read an order financial history',
        description: 'Owner-scoped and read-only. Entries are ordered by chain_sequence. Credits add to balance, debits subtract, memos have no balance effect.',
        tags: ['Ledger'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId'), new OA\Parameter(ref: '#/components/parameters/Page'), new OA\Parameter(ref: '#/components/parameters/PerPage')],
        responses: [
            new OA\Response(response: 200, description: 'Paginated immutable ledger entries.', content: new OA\JsonContent(ref: '#/components/schemas/LedgerPage', examples: [
                new OA\Examples(example: 'OrderHistory', value: ['data' => [['id' => '01J00000000000000000000000', 'order_id' => 1, 'payment_id' => null, 'chain_sequence' => 1,
                    'type' => 'payment_initiated', 'direction' => 'memo', 'amount' => '39.98', 'currency' => 'USD', 'gateway' => 'credit_card',
                    'transaction_id' => null, 'idempotency_key' => 'payment-request-001', 'metadata' => ['last4' => '4242'], 'previous_hash' => null,
                    'entry_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'created_at' => '2026-10-21T00:00:00.000000Z']],
                    'links' => ['first' => '/api/orders/1/ledger?page=1', 'last' => '/api/orders/1/ledger?page=1', 'prev' => null, 'next' => null],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1]]),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Get(
        path: '/api/payments/{payment}/ledger',
        operationId: 'paymentLedger',
        summary: 'Read a payment financial history',
        description: 'Read-only, owner-scoped payment events in chain order. Another user receives 404.',
        tags: ['Ledger'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PaymentId'), new OA\Parameter(ref: '#/components/parameters/Page'), new OA\Parameter(ref: '#/components/parameters/PerPage')],
        responses: [
            new OA\Response(response: 200, description: 'Paginated immutable payment events.', content: new OA\JsonContent(ref: '#/components/schemas/LedgerPage', example: ['data' => [['id' => '01J00000000000000000000000', 'order_id' => 1, 'payment_id' => 1, 'chain_sequence' => 1, 'type' => 'payment_initiated', 'direction' => 'memo', 'amount' => '39.98', 'currency' => 'USD', 'gateway' => 'credit_card', 'transaction_id' => null, 'idempotency_key' => 'payment-example-001', 'metadata' => ['last4' => '4242'], 'previous_hash' => null, 'entry_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'created_at' => '2026-10-21T00:00:00Z']], 'links' => ['first' => '/api/payments/1/ledger?page=1', 'last' => '/api/payments/1/ledger?page=1', 'prev' => null, 'next' => null], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1]])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function forPayment(): void
    {
    }
}
