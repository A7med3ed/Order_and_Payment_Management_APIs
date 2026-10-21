<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_and_model_writes_use_the_mysql_testing_database(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('app_testing', DB::connection()->getDatabaseName());

        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_local_seed_is_repeatable_and_uses_valid_login_credentials(): void
    {
        $this->seed();
        $this->seed();
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
        $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'StrongPass123!'])->assertOk()->assertJsonStructure(['data' => ['access_token']]);
    }
}
