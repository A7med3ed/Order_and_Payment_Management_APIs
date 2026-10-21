<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\TokenResource;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Shared\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->resource(new UserResource($this->auth->register($request->toDto())), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->resource(new TokenResource($this->auth->login($request->toDto())));
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return response()->noContent();
    }

    public function me(): JsonResponse
    {
        return $this->resource(new UserResource($this->auth->me()));
    }
}
