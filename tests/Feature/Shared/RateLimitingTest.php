<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_limit_has_standard_body_and_retry_headers_and_normalizes_email(): void
    {
        config(['rate_limits.login_per_minute' => 2]);
        $credentials = ['email' => 'user@example.com', 'password' => 'wrong-password'];

        $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();
        $this->postJson('/api/auth/login', array_replace($credentials, ['email' => ' USER@EXAMPLE.COM ']))
            ->assertUnauthorized();
        $this->postJson('/api/auth/login', $credentials)
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After');

        $this->postJson('/api/auth/login', array_replace($credentials, ['email' => 'other@example.com']))
            ->assertUnauthorized();

        $this->travel(61)->seconds();
        $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();
    }

    public function test_registration_limit_is_per_ip_and_uses_config(): void
    {
        config(['rate_limits.register_per_minute' => 1]);

        $this->postJson('/api/auth/register', [])->assertUnprocessable();
        $this->postJson('/api/auth/register', [])->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])
            ->postJson('/api/auth/register', [])->assertUnprocessable();
    }

    public function test_authenticated_limits_are_per_user(): void
    {
        config(['rate_limits.api_per_minute' => 1]);

        $firstToken = $this->app['auth']->guard('api')->login(User::factory()->create());
        $this->resetAuthentication();
        $secondUser = User::factory()->create();
        $secondToken = $this->app['auth']->guard('api')->login($secondUser);
        $this->resetAuthentication();

        $this->withToken($firstToken)->getJson('/api/auth/me')->assertOk();
        $this->resetAuthentication();
        $this->withToken($firstToken)->getJson('/api/auth/me')->assertStatus(429);
        $this->resetAuthentication();
        $this->withToken($secondToken)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.id', $secondUser->id);
    }

    public function test_malformed_email_input_returns_validation_errors_instead_of_a_server_error(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['invalid'], 'password' => 'some-password'])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
