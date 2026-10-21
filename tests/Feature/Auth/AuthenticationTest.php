<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(): array
    {
        return [
            'name' => 'Jane Doe', 'email' => ' Jane@Example.com ',
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
        ];
    }

    public function test_registration_login_and_current_user_flow(): void
    {
        $this->postJson('/api/auth/register', $this->registration())
            ->assertCreated()
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $user = User::query()->sole();
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));

        $login = $this->postJson('/api/auth/login', ['email' => 'JANE@example.com', 'password' => 'StrongPass123!'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonMissingPath('data.user.password');

        $this->resetAuthentication();
        $this->withToken($login->json('data.access_token'))->get('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    public function test_duplicate_email_is_rejected_after_normalization(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/register', $this->registration())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_registration_is_json_without_an_accept_header(): void
    {
        $this->post('/api/auth/register', [
            'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_credentials_return_a_standard_401(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonMissingPath('data.access_token');
    }

    public function test_multibyte_passwords_exceeding_bcrypt_capacity_are_validation_errors(): void
    {
        $data = $this->registration();
        $data['password'] = str_repeat('é', 36) . 'A1';
        $data['password_confirmation'] = $data['password'];

        $this->postJson('/api/auth/register', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => $data['password']])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_and_malformed_tokens_are_rejected_without_accept_headers(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->resetAuthentication();
        $this->withToken('not-a-jwt')->get('/api/auth/me')
            ->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $token = $this->app['auth']->guard('api')->login(User::factory()->create());
        $this->resetAuthentication();
        $this->travel(61)->minutes();

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_logout_invalidates_the_token(): void
    {
        $token = $this->app['auth']->guard('api')->login(User::factory()->create());
        $this->resetAuthentication();

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->resetAuthentication();
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
