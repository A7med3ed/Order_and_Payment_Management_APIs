<?php

declare(strict_types=1);

namespace App\Modules\Orders\Docs;

use OpenApi\Attributes as OA;

final class OrderEndpoints
{
    #[OA\Get(
        path: '/api/orders',
        operationId: 'listOrders',
        summary: 'List and filter your orders',
        description: 'Owner-scoped, newest first. per_page defaults to 15 and has a maximum of 100.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Page'), new OA\Parameter(ref: '#/components/parameters/PerPage'), new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'cancelled']))],
        responses: [
            new OA\Response(response: 200, description: 'Successful order response.', content: new OA\JsonContent(ref: '#/components/schemas/OrderPage', examples: [new OA\Examples(example: 'listOrders', value: ['data' => [['id' => 1, 'user_id' => 1, 'customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com',
        'status' => 'pending', 'total_amount' => '39.98', 'items' => [['id' => 1, 'product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']],
        'created_at' => '2026-10-21T00:00:00.000000Z', 'updated_at' => '2026-10-21T00:00:00.000000Z']], 'links' => ['first' => '/api/orders?page=1', 'last' => '/api/orders?page=1', 'prev' => null, 'next' => null], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1]])])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/api/orders',
        operationId: 'createOrder',
        summary: 'Place an order',
        description: 'Totals and initial pending status are computed by the aggregate. Client total_amount, status, and user_id fields are ignored.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateOrder', examples: [new OA\Examples(example: 'CreateOrder', value: ['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com', 'items' => [['product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']]])])),
        responses: [
            new OA\Response(response: 201, description: 'Successful order response.', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse', examples: [new OA\Examples(example: 'createOrder', value: ['data' => ['id' => 1, 'user_id' => 1, 'customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com',
        'status' => 'pending', 'total_amount' => '39.98', 'items' => [['id' => 1, 'product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']],
        'created_at' => '2026-10-21T00:00:00.000000Z', 'updated_at' => '2026-10-21T00:00:00.000000Z']])])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/api/orders/{order}',
        operationId: 'showOrder',
        summary: 'Get an order',
        description: 'Other users receive 404, matching missing resources.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        responses: [
            new OA\Response(response: 200, description: 'Successful order response.', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse', examples: [new OA\Examples(example: 'showOrder', value: ['data' => ['id' => 1, 'user_id' => 1, 'customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com',
        'status' => 'pending', 'total_amount' => '39.98', 'items' => [['id' => 1, 'product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']],
        'created_at' => '2026-10-21T00:00:00.000000Z', 'updated_at' => '2026-10-21T00:00:00.000000Z']])])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Put(
        path: '/api/orders/{order}',
        operationId: 'replaceOrder',
        summary: 'Update order details or items',
        description: 'Only pending orders are editable. Provided items replace the entire item collection; omitted fields remain unchanged.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateOrder', examples: [new OA\Examples(example: 'UpdateOrder', value: ['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com', 'items' => [['product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']]])])),
        responses: [
            new OA\Response(response: 200, description: 'Successful order response.', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse', examples: [new OA\Examples(example: 'replaceOrder', value: ['data' => ['id' => 1, 'user_id' => 1, 'customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com',
        'status' => 'pending', 'total_amount' => '39.98', 'items' => [['id' => 1, 'product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']],
        'created_at' => '2026-10-21T00:00:00.000000Z', 'updated_at' => '2026-10-21T00:00:00.000000Z']])])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 409, description: 'Domain state conflict.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', examples: [
                new OA\Examples(example: 'OrderHasPayments', value: ['message' => 'An order with payments cannot be deleted.', 'code' => 'ORDER_HAS_PAYMENTS']),
                new OA\Examples(example: 'InvalidTransition', value: ['message' => 'Cannot change order status from cancelled to confirmed.', 'code' => 'INVALID_STATUS_TRANSITION']),
                new OA\Examples(example: 'OrderNotEditable', value: ['message' => 'Only pending orders are editable.', 'code' => 'ORDER_NOT_EDITABLE']),
            ])),
        ],
    )]
    public function put(): void
    {
    }

    #[OA\Patch(
        path: '/api/orders/{order}',
        operationId: 'updateOrder',
        summary: 'Partially update an order',
        description: 'Only pending orders are editable. Provided items replace the complete item collection.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateOrder', examples: [new OA\Examples(example: 'UpdateOrder', value: ['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com', 'items' => [['product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']]])])),
        responses: [
            new OA\Response(response: 200, description: 'Successful order response.', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse', examples: [new OA\Examples(example: 'updateOrder', value: ['data' => ['id' => 1, 'user_id' => 1, 'customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com',
        'status' => 'pending', 'total_amount' => '39.98', 'items' => [['id' => 1, 'product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']],
        'created_at' => '2026-10-21T00:00:00.000000Z', 'updated_at' => '2026-10-21T00:00:00.000000Z']])])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 409, description: 'Domain state conflict.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', examples: [
                new OA\Examples(example: 'OrderHasPayments', value: ['message' => 'An order with payments cannot be deleted.', 'code' => 'ORDER_HAS_PAYMENTS']),
                new OA\Examples(example: 'InvalidTransition', value: ['message' => 'Cannot change order status from cancelled to confirmed.', 'code' => 'INVALID_STATUS_TRANSITION']),
                new OA\Examples(example: 'OrderNotEditable', value: ['message' => 'Only pending orders are editable.', 'code' => 'ORDER_NOT_EDITABLE']),
            ])),
        ],
    )]
    public function update(): void
    {
    }

    #[OA\Patch(
        path: '/api/orders/{order}/status',
        operationId: 'changeOrderStatus',
        summary: 'Confirm or cancel an order',
        description: 'Allowed transitions: pending to confirmed/cancelled; confirmed to cancelled only without successful payment. Cancelled is terminal.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], type: 'object', properties: [new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'cancelled'])], examples: [
            new OA\Examples(example: 'ConfirmOrder', value: ['status' => 'confirmed']),
            new OA\Examples(example: 'CancelOrder', value: ['status' => 'cancelled']),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Successful order response.', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse', examples: [new OA\Examples(example: 'changeOrderStatus', value: ['data' => ['id' => 1, 'user_id' => 1, 'customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com',
        'status' => 'confirmed', 'total_amount' => '39.98', 'items' => [['id' => 1, 'product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']],
        'created_at' => '2026-10-21T00:00:00.000000Z', 'updated_at' => '2026-10-21T00:00:00.000000Z']])])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 409, description: 'Domain state conflict.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', examples: [
                new OA\Examples(example: 'OrderHasPayments', value: ['message' => 'An order with payments cannot be deleted.', 'code' => 'ORDER_HAS_PAYMENTS']),
                new OA\Examples(example: 'InvalidTransition', value: ['message' => 'Cannot change order status from cancelled to confirmed.', 'code' => 'INVALID_STATUS_TRANSITION']),
                new OA\Examples(example: 'OrderNotEditable', value: ['message' => 'Only pending orders are editable.', 'code' => 'ORDER_NOT_EDITABLE']),
            ])),
        ],
    )]
    public function status(): void
    {
    }

    #[OA\Delete(
        path: '/api/orders/{order}',
        operationId: 'deleteOrder',
        summary: 'Delete an order without payments',
        description: 'Any existing payment, including failed attempts, blocks deletion. Until Payments is implemented, a temporary no-payments guard is bound.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        responses: [
            new OA\Response(response: 204, description: 'Order and its items deleted; no response body.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 409, description: 'Domain state conflict.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', examples: [
                new OA\Examples(example: 'OrderHasPayments', value: ['message' => 'An order with payments cannot be deleted.', 'code' => 'ORDER_HAS_PAYMENTS']),
                new OA\Examples(example: 'InvalidTransition', value: ['message' => 'Cannot change order status from cancelled to confirmed.', 'code' => 'INVALID_STATUS_TRANSITION']),
                new OA\Examples(example: 'OrderNotEditable', value: ['message' => 'Only pending orders are editable.', 'code' => 'ORDER_NOT_EDITABLE']),
            ])),
        ],
    )]
    public function destroy(): void
    {
    }

}
