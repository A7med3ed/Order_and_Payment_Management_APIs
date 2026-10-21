<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api', 'api/*', 'documentation/*') && ! $request->expectsJson()) {
            return null;
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        if ($exception instanceof ApiException) {
            $headers = array_merge($headers, $exception->headers);
        }
        $status = match (true) {
            $exception instanceof ApiException => $exception->httpStatus,
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException, $exception instanceof JWTException => 401,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        [$code, $message] = match ($status) {
            401 => [ErrorCode::Unauthenticated, 'Authentication is required or the token is invalid.'],
            403 => [ErrorCode::Forbidden, 'You are not allowed to perform this action.'],
            404 => [ErrorCode::NotFound, 'The requested resource was not found.'],
            405 => [ErrorCode::MethodNotAllowed, 'This HTTP method is not allowed.'],
            409 => [ErrorCode::Conflict, 'The request conflicts with the current resource state.'],
            422 => [ErrorCode::ValidationError, 'The given data was invalid.'],
            429 => [ErrorCode::TooManyRequests, 'Too many requests. Please try again later.'],
            default => [ErrorCode::ServerError, 'An unexpected error occurred.'],
        };

        if ($exception instanceof ApiException) {
            $code = $exception->errorCode;
            $message = $exception->getMessage();
        }

        $body = ['message' => $message, 'code' => $code->value];
        $errors = match (true) {
            $exception instanceof ValidationException => $exception->errors(),
            $exception instanceof ApiException => $exception->errors,
            default => [],
        };

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }
}
