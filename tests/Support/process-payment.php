<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.connections.mysql.database') !== 'app_testing' || ! $app->environment('testing')) {
    throw new RuntimeException('Payment workers require the dedicated test database.');
}
[$script, $orderId, $barrier, $worker, $key] = $argv;
$app->make(\App\Modules\Payments\Gateways\PaymentGatewayManager::class)->extend('example', fn () => new class ($barrier) implements \App\Modules\Payments\Gateways\Contracts\PaymentGatewayInterface {
    public function __construct(private readonly string $barrier)
    {
    }
    public function method(): string
    {
        return 'example';
    }
    public function rules(): array
    {
        return ['reference' => ['required', 'string']];
    }
    public function charge(\App\Modules\Payments\Gateways\DTOs\GatewayChargeRequest $request): \App\Modules\Payments\Gateways\DTOs\GatewayChargeResult
    {
        if (\Illuminate\Support\Facades\DB::transactionLevel() !== 0) {
            throw new RuntimeException('Gateway ran inside a database transaction.');
        }
        file_put_contents($this->barrier.'.charges', "charge\n", FILE_APPEND | LOCK_EX);
        usleep(500000);
        return \App\Modules\Payments\Gateways\DTOs\GatewayChargeResult::success('concurrency_'.$request->reference());
    }
});
file_put_contents($barrier.'.ready.'.$worker, 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Payment barrier timed out.');
    }
    usleep(10000);
    clearstatcache(true, $barrier);
}
$request = \Illuminate\Http\Request::create('/api/orders/'.$orderId.'/payments', 'POST', server: [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.getenv('PAYMENT_TEST_TOKEN'), 'HTTP_IDEMPOTENCY_KEY' => $key,
], content: json_encode(['method' => 'example', 'payment_details' => ['reference' => 'concurrent-test']], JSON_THROW_ON_ERROR));
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($barrier.'.result.'.$worker, json_encode(['status' => $response->getStatusCode(), 'code' => $body['code'] ?? null], JSON_THROW_ON_ERROR));
$kernel->terminate($request, $response);
