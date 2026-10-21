<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LedgerEntry', type: 'object', required: ['id', 'order_id', 'chain_sequence', 'type', 'direction', 'amount', 'currency', 'entry_hash', 'created_at'], properties: [
    new OA\Property(property: 'id', type: 'string', example: '01J00000000000000000000000'),
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'payment_id', type: 'integer', nullable: true),
    new OA\Property(property: 'chain_sequence', type: 'integer', minimum: 1),
    new OA\Property(property: 'type', type: 'string', enum: ['payment_initiated', 'payment_succeeded', 'payment_failed', 'payment_reversed']),
    new OA\Property(property: 'direction', type: 'string', enum: ['credit', 'debit', 'memo']),
    new OA\Property(property: 'amount', type: 'string', example: '39.98'),
    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    new OA\Property(property: 'gateway', type: 'string', nullable: true),
    new OA\Property(property: 'transaction_id', type: 'string', nullable: true),
    new OA\Property(property: 'idempotency_key', type: 'string', nullable: true),
    new OA\Property(property: 'metadata', type: 'object', additionalProperties: true),
    new OA\Property(property: 'previous_hash', type: 'string', nullable: true),
    new OA\Property(property: 'entry_hash', type: 'string'),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
])]
#[OA\Schema(schema: 'LedgerPage', type: 'object', required: ['data', 'links', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LedgerEntry')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
])]
final class LedgerSchemas
{
}
