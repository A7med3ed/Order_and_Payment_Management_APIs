<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Idempotency\DTOs\{IdempotencyRequestData, StoredResponseData};
use App\Modules\Shared\Idempotency\Models\IdempotencyKey;
use App\Modules\Shared\Idempotency\Services\IdempotencyService;
use App\Modules\Shared\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Route};
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

final class IdempotencyTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private string $token;
    private int $effects = 0;
    private string $mode = 'success';

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->token = $this->app['auth']->guard('api')->setTTL(2880)->login($this->owner);
        $this->resetAuthentication();
        foreach (['idempotency', 'idempotency-other'] as $path) {
            Route::post('/api/_test/'.$path, function (Request $request) {
                $request->validate(['value' => ['required', 'integer']]);
                if ($this->mode === 'server') {
                    return response()->json(['code' => 'SERVER_ERROR'], 500);
                }
                if ($this->mode === 'throw') {
                    throw new RuntimeException('Fixture exception');
                }
                return response()->json(['effect' => ++$this->effects, 'value' => $request->integer('value')], 201, ['Location' => '/api/_test/result']);
            })->middleware(['api', 'auth:api', 'throttle:api', 'idempotent']);
        }
    }

    private function send(?string $key, array $body = ['value' => 1], string $path = '/api/_test/idempotency'): TestResponse
    {
        $this->resetAuthentication();
        $this->withToken($this->token);
        $this->withHeaders(['Idempotency-Key' => $key ?? '']);
        return $this->postJson($path, $body);
    }

    private function hash(array $body = ['value' => 1]): string
    {
        return hash('sha256', "POST\napi/_test/idempotency\n[]\n".CanonicalJson::encode((object) $body));
    }

    public function test_new_key_executes_once_and_replays_the_exact_status_body_and_location(): void
    {
        $first = $this->send('request-key-1')->assertCreated();
        $second = $this->send('request-key-1')->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertHeader('Location', '/api/_test/result');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, $this->effects);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_canonical_json_key_order_does_not_change_request_identity(): void
    {
        $this->send('canonical-key', ['value' => 1, 'metadata' => ['b' => 2, 'a' => 1]])->assertCreated();
        $this->send('canonical-key', ['metadata' => ['a' => 1, 'b' => 2], 'value' => 1])->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, $this->effects);
    }

    public function test_mismatch_in_body_or_endpoint_is_rejected_without_side_effects(): void
    {
        $this->send('mismatch-key')->assertCreated();
        $this->send('mismatch-key', ['value' => 2])->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->send('mismatch-key', ['value' => 1], '/api/_test/idempotency-other')->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(1, $this->effects);
    }

    public function test_missing_or_invalid_headers_are_validation_errors(): void
    {
        $this->send(null)->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
        $this->send('valid-key')->assertCreated();
        $this->send('bad key with spaces')->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->send('short')->assertUnprocessable();
        $this->assertSame(1, $this->effects);
    }

    public function test_processing_keys_return_conflict_and_retry_after(): void
    {
        app(IdempotencyService::class)->begin(new IdempotencyRequestData($this->owner->id, 'processing-key', $this->hash()));
        $this->send('processing-key')->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_IN_PROGRESS')->assertHeader('Retry-After', '1');
        $this->assertSame(0, $this->effects);
    }

    public function test_validation_errors_release_the_key_for_a_corrected_retry(): void
    {
        $this->send('validation-key', [])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->send('validation-key')->assertCreated();
    }

    public function test_server_responses_and_uncaught_exceptions_release_the_key(): void
    {
        Log::spy();
        $this->mode = 'server';
        $this->send('server-error-key')->assertStatus(500);
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->mode = 'throw';
        $this->send('server-error-key')->assertStatus(500);
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->mode = 'success';
        $this->send('server-error-key')->assertCreated();
    }

    public function test_keys_are_case_sensitive_and_scoped_per_user(): void
    {
        $this->send('CaseSensitiveKey')->assertCreated();
        $this->send('casesensitivekey')->assertCreated();
        $this->token = $this->app['auth']->guard('api')->login(User::factory()->create());
        $this->send('CaseSensitiveKey')->assertCreated()->assertJsonPath('effect', 3);
        $this->assertDatabaseCount('idempotency_keys', 3);
    }

    public function test_ttl_expiry_allows_a_new_request_and_pruning_removes_expired_keys(): void
    {
        $this->send('expiring-key')->assertCreated();
        $this->travel(25)->hours();
        $this->send('expiring-key', ['value' => 2])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(2, $this->effects);
        $this->travel(25)->hours();
        $this->artisan('idempotency:prune')->assertSuccessful();
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_stale_request_cannot_release_or_complete_a_new_reservation(): void
    {
        $service = app(IdempotencyService::class);
        $request = new IdempotencyRequestData($this->owner->id, 'lease-fence-key', $this->hash());
        $old = $service->begin($request);
        $this->travel(25)->hours();
        $new = $service->begin($request);
        $service->release($old);
        $service->complete($old, new StoredResponseData(201, '{"old":true}'));
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('processing', IdempotencyKey::query()->findOrFail($new->id)->status->value);
        $service->complete($new, new StoredResponseData(201, '{"new":true}'));
        $this->assertSame('{"new":true}', IdempotencyKey::query()->findOrFail($new->id)->response_body);
    }

    public function test_request_credentials_are_hashed_and_not_stored(): void
    {
        $this->send('sensitive-body-key', ['value' => 1, 'payment_details' => ['card_number' => '4242424242424242', 'cvv' => '123']])->assertCreated();
        $serialized = json_encode((array) DB::table('idempotency_keys')->first(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('4242424242424242', $serialized);
        $this->assertStringNotContainsString('cvv', $serialized);
    }
}
