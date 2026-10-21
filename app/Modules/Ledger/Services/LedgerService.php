<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Contracts\{LedgerRecorderInterface, LedgerReaderInterface};
use App\Modules\Ledger\DTOs\{LedgerEntryData, LedgerFilterData};
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Shared\DTOs\PaginationData;
use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};
use App\Modules\Shared\Support\{Money, SensitiveData};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OverflowException;

final readonly class LedgerService implements LedgerRecorderInterface, LedgerReaderInterface
{
    public function __construct(private LedgerHasher $hasher)
    {
    }

    public function record(LedgerEntryData $data): void
    {
        try {
            $amount = Money::format($data->amount);
        } catch (InvalidArgumentException|OverflowException $exception) {
            throw new ApiException(ErrorCode::ValidationError, 422, 'Invalid ledger amount.', previous: $exception);
        }
        if ($data->orderId < 1 || $data->userId < 1 || Money::compare($amount, '0') < 0
            || preg_match('/^[A-Z]{3}$/D', $data->currency) !== 1 || $data->direction !== $data->type->direction()) {
            throw new ApiException(ErrorCode::ValidationError, 422, 'Invalid ledger identifiers, currency, amount, or event direction.');
        }

        DB::transaction(function () use ($data, $amount): void {
            DB::table('ledger_chain_heads')->insertOrIgnore(['order_id' => $data->orderId, 'last_sequence' => 0, 'last_hash' => null]);
            $head = DB::table('ledger_chain_heads')->where('order_id', $data->orderId)->lockForUpdate()->first();
            $previous = LedgerEntry::query()->where('order_id', $data->orderId)->orderByDesc('chain_sequence')->lockForUpdate()->first();
            $sequence = ($previous?->chain_sequence ?? 0) + 1;
            if ((int) $head->last_sequence !== $sequence - 1 || $head->last_hash !== $previous?->entry_hash) {
                throw new \RuntimeException('The ledger chain head does not match its entries.');
            }

            $entry = new LedgerEntry();
            $entry->forceFill([
                'id' => (string) Str::ulid(), 'order_id' => $data->orderId, 'user_id' => $data->userId,
                'payment_id' => $data->paymentId, 'chain_sequence' => $sequence, 'type' => $data->type,
                'direction' => $data->direction, 'amount' => $amount, 'currency' => $data->currency,
                'gateway' => $data->gateway, 'transaction_id' => $data->transactionId,
                'idempotency_key' => $data->idempotencyKey, 'metadata' => SensitiveData::sanitize($data->metadata),
                'previous_hash' => $previous?->entry_hash, 'created_at' => ($data->createdAt ?? CarbonImmutable::now('UTC'))->utc(),
            ]);
            $entry->entry_hash = $this->hasher->hash($entry);
            $entry->save();
            DB::table('ledger_chain_heads')->where('order_id', $data->orderId)
                ->update(['last_sequence' => $sequence, 'last_hash' => $entry->entry_hash]);
        }, 5);
    }

    public function forOrder(LedgerFilterData $data): LengthAwarePaginator
    {
        return LedgerEntry::query()->where('order_id', $data->orderId)->where('user_id', $data->userId)
            ->orderBy('chain_sequence')->paginate($data->pagination->perPage, ['*'], 'page', $data->pagination->page)->withQueryString();
    }

    public function forPayment(int $paymentId, int $userId, PaginationData $pagination): LengthAwarePaginator
    {
        return LedgerEntry::query()->where('payment_id', $paymentId)->where('user_id', $userId)
            ->orderBy('order_id')->orderBy('chain_sequence')->paginate($pagination->perPage, ['*'], 'page', $pagination->page)->withQueryString();
    }
}
