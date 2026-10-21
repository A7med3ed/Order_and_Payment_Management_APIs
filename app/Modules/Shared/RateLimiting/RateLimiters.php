<?php

declare(strict_types=1);

namespace App\Modules\Shared\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class RateLimiters
{
    public function register(): void
    {
        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return Limit::perMinute(max(1, (int) config('rate_limits.login_per_minute')))
                ->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth-register', fn (Request $request): Limit =>
            Limit::perMinute(max(1, (int) config('rate_limits.register_per_minute')))->by($request->ip()));

        RateLimiter::for('api', fn (Request $request): Limit =>
            Limit::perMinute(max(1, (int) config('rate_limits.api_per_minute')))
                ->by($request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip()));

        RateLimiter::for('documentation', fn (Request $request): Limit =>
            Limit::perMinute(max(1, (int) config('rate_limits.documentation_per_minute')))->by($request->ip()));
    }
}
