<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\DTOs\TokenData;
use App\Modules\Auth\Models\User;
use App\Modules\Shared\Exceptions\ApiException;
use App\Modules\Shared\Exceptions\ErrorCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

final readonly class AuthService
{
    public function __construct(private AuthFactory $auth)
    {
    }

    public function register(RegisterData $data): User
    {
        try {
            return User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new ApiException(
                ErrorCode::ValidationError,
                422,
                'The given data was invalid.',
                ['email' => ['This email address is already registered.']],
                $exception,
            );
        }
    }

    public function login(LoginData $data): TokenData
    {
        $token = $this->guard()->attempt(['email' => $data->email, 'password' => $data->password])
            ?: throw new ApiException(ErrorCode::Unauthenticated, 401, 'The provided credentials are invalid.');

        return new TokenData($token, $this->guard()->factory()->getTTL() * 60, $this->me());
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    public function me(): User
    {
        return $this->guard()->user()
            ?? throw new ApiException(ErrorCode::Unauthenticated, 401, 'Authentication is required.');
    }

    private function guard(): JWTGuard
    {
        return $this->auth->guard('api');
    }
}
