<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Shared\Exceptions\ApiException;
use App\Modules\Shared\Exceptions\ErrorCode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class ApiErrorsTest extends TestCase
{
    public function test_not_found_and_method_not_allowed_use_the_standard_format(): void
    {
        $this->get('/api/missing')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->put('/api/auth/login')->assertStatus(405)
            ->assertJsonPath('code', 'METHOD_NOT_ALLOWED')->assertHeader('Allow');
    }

    public function test_forbidden_and_domain_conflicts_use_the_standard_format(): void
    {
        Route::get('/api/_test/forbidden', fn () => abort(403))->middleware('api');
        Route::get('/api/_test/conflict', function (): never {
            throw new ApiException(ErrorCode::OrderHasPayments, 409, 'This order has payments.');
        })->middleware('api');

        $this->get('/api/_test/forbidden')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->get('/api/_test/conflict')->assertStatus(409)
            ->assertJsonPath('code', 'ORDER_HAS_PAYMENTS')
            ->assertJsonPath('message', 'This order has payments.');
    }

    public function test_unexpected_errors_hide_internal_details_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        Route::get('/api/_test/error', function (): never {
            throw new RuntimeException('Internal exception detail');
        })->middleware('api');

        $this->get('/api/_test/error')->assertStatus(500)
            ->assertExactJson(['message' => 'An unexpected error occurred.', 'code' => 'SERVER_ERROR']);
    }
}
