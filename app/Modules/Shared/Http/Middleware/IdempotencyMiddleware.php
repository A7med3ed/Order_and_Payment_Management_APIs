<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};
use App\Modules\Shared\Idempotency\DTOs\{IdempotencyRequestData, StoredResponseData};
use App\Modules\Shared\Idempotency\Services\IdempotencyService;
use App\Modules\Shared\Support\CanonicalJson;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class IdempotencyMiddleware
{
    public function __construct(private IdempotencyService $keys)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? throw new AuthenticationException();
        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            throw new ApiException(ErrorCode::IdempotencyKeyRequired, 422, 'The Idempotency-Key header is required.');
        }
        if (preg_match('/^[A-Za-z0-9_.:-]{8,255}$/D', $key) !== 1) {
            throw new ApiException(
                ErrorCode::ValidationError,
                422,
                'The idempotency key must be 8–255 ASCII letters, numbers, dots, colons, underscores, or hyphens.',
                ['Idempotency-Key' => ['Invalid idempotency key format.']]
            );
        }

        try {
            if ($request->getContent() !== '' && ! $request->isJson()) {
                throw new JsonException('Expected JSON.');
            }
            $body = $request->getContent() === '' ? new \stdClass() : json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(ErrorCode::ValidationError, 422, 'A valid JSON request body is required.', ['body' => ['Invalid JSON request body.']]);
        }
        $hash = hash('sha256', $request->method()."\n".$request->path()."\n".CanonicalJson::encode($request->query())."\n".CanonicalJson::encode($body));
        $context = $this->keys->begin(new IdempotencyRequestData((int) $user->getAuthIdentifier(), $key, $hash));
        if ($context->replay !== null) {
            return new Response(
                $context->replay->body,
                $context->replay->status,
                [...$context->replay->headers, 'Idempotent-Replayed' => 'true']
            );
        }

        try {
            $response = $next($request);
            $content = $response->getContent();
            $decoded = is_string($content) ? json_decode($content, true) : null;
            if ($response->getStatusCode() >= 500 || $content === false
                || ($response->getStatusCode() === 422 && ($decoded['code'] ?? null) === ErrorCode::ValidationError->value)) {
                $this->keys->release($context);
            } else {
                $headers = [];
                foreach (['Content-Type', 'Location', 'Content-Language'] as $name) {
                    if ($response->headers->has($name)) {
                        $headers[$name] = $response->headers->get($name);
                    }
                }
                $this->keys->complete($context, new StoredResponseData($response->getStatusCode(), $content, $headers));
            }
            return $response;
        } catch (Throwable $exception) {
            $this->keys->release($context);
            throw $exception;
        }
    }
}
