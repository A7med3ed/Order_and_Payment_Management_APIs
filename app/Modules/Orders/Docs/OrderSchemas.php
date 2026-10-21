<?php

declare(strict_types=1);

namespace App\Modules\Orders\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'OrderItem', type: 'object', required: ['id', 'product_name', 'quantity', 'price'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 1),
    new OA\Property(property: 'product_name', type: 'string', example: 'Book'),
    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 2),
    new OA\Property(property: 'price', type: 'string', example: '19.99'),
])]
#[OA\Schema(schema: 'Order', type: 'object', required: ['id', 'user_id', 'customer_name', 'customer_email', 'status', 'total_amount', 'items'], properties: [
    new OA\Property(property: 'id', type: 'integer', example: 1),
    new OA\Property(property: 'user_id', type: 'integer', example: 1),
    new OA\Property(property: 'customer_name', type: 'string', example: 'Jane Doe'),
    new OA\Property(property: 'customer_email', type: 'string', format: 'email', example: 'jane@example.com'),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'cancelled'], example: 'pending'),
    new OA\Property(property: 'total_amount', type: 'string', readOnly: true, example: '39.98'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
])]
#[OA\Schema(schema: 'OrderResponse', type: 'object', required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])]
#[OA\Schema(schema: 'OrderPage', type: 'object', required: ['data', 'links', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
])]
#[OA\Schema(schema: 'OrderItemInput', type: 'object', required: ['product_name', 'quantity', 'price'], properties: [
    new OA\Property(property: 'product_name', type: 'string', maxLength: 255),
    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 4294967295),
    new OA\Property(property: 'price', type: 'string', description: 'Non-negative decimal string; at most two decimal places and DECIMAL(12,2) capacity.', pattern: '^\d+(\.\d{1,2})?$', example: '19.99'),
])]
#[OA\Schema(schema: 'CreateOrder', type: 'object', required: ['customer_name', 'customer_email', 'items'], properties: [
    new OA\Property(property: 'customer_name', type: 'string', maxLength: 255),
    new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255),
    new OA\Property(property: 'items', type: 'array', minItems: 1, maxItems: 1000, items: new OA\Items(ref: '#/components/schemas/OrderItemInput')),
])]
#[OA\Schema(schema: 'UpdateOrder', type: 'object', minProperties: 1, properties: [
    new OA\Property(property: 'customer_name', type: 'string', maxLength: 255),
    new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255),
    new OA\Property(property: 'items', type: 'array', minItems: 1, maxItems: 1000, items: new OA\Items(ref: '#/components/schemas/OrderItemInput')),
])]
final class OrderSchemas
{
}
