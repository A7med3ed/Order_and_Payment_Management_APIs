<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency\Services;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};
use App\Modules\Shared\Idempotency\DTOs\{IdempotencyRequestData, IdempotencyContextData, StoredResponseData};
use App\Modules\Shared\Idempotency\Enums\IdempotencyStatus;
use App\Modules\Shared\Idempotency\Models\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;

final class IdempotencyService
{
    public function begin(IdempotencyRequestData $data): IdempotencyContextData
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            IdempotencyKey::query()->where('user_id', $data->userId)->where('key', $data->key)
                ->where('expires_at', '<=', now())->delete();
            try {
                $key = IdempotencyKey::query()->create([
                    'user_id' => $data->userId, 'key' => $data->key, 'request_hash' => $data->requestHash,
                    'status' => IdempotencyStatus::Processing,
                    'expires_at' => now()->addHours(max(1, (int) config('idempotency.ttl_hours'))),
                ]);
                return new IdempotencyContextData((int) $key->id, $data->userId, $data->requestHash);
            } catch (UniqueConstraintViolationException) {
                $key = IdempotencyKey::query()->where('user_id', $data->userId)->where('key', $data->key)->first();
                if ($key === null || $key->expires_at->isPast()) {
                    continue;
                }
                if (! hash_equals($key->request_hash, $data->requestHash)) {
                    throw new ApiException(ErrorCode::IdempotencyKeyReused, 422, 'This idempotency key was used for a different request.');
                }
                if ($key->status === IdempotencyStatus::Processing) {
                    throw $this->inProgress();
                }
                return new IdempotencyContextData(
                    (int) $key->id,
                    $data->userId,
                    $data->requestHash,
                    new StoredResponseData($key->response_status, $key->response_body, $key->response_headers ?? [])
                );
            }
        }
        throw $this->inProgress();
    }

    public function complete(IdempotencyContextData $context, StoredResponseData $response): void
    {
        IdempotencyKey::query()->whereKey($context->id)->where('user_id', $context->userId)
            ->where('request_hash', $context->requestHash)->where('status', IdempotencyStatus::Processing->value)
            ->update(['status' => IdempotencyStatus::Completed->value, 'response_status' => $response->status,
                'response_body' => json_encode($response->body, JSON_THROW_ON_ERROR),
                'response_headers' => json_encode($response->headers, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function release(IdempotencyContextData $context): void
    {
        IdempotencyKey::query()->whereKey($context->id)->where('user_id', $context->userId)
            ->where('request_hash', $context->requestHash)->where('status', IdempotencyStatus::Processing->value)->delete();
    }

    public function prune(): int
    {
        return IdempotencyKey::query()->where('expires_at', '<=', now())->delete();
    }

    private function inProgress(): ApiException
    {
        return new ApiException(ErrorCode::IdempotencyInProgress, 409, 'A request with this idempotency key is still processing.', headers: ['Retry-After' => '1']);
    }
}
