<?php

declare(strict_types=1);

// Convert the exported OpenAPI operations and their saved examples, then add a runnable walkthrough.
$root = dirname(__DIR__);
$spec = json_decode(file_get_contents($root.'/docs/openapi/openapi.json'), true, flags: JSON_THROW_ON_ERROR);
$operations = [];
foreach ($spec['paths'] as $path => $methods) {
    foreach ($methods as $method => $operation) {
        if (isset($operation['operationId'])) {
            $operations[$operation['operationId']] = [$path, $method, $operation];
        }
    }
}

function resolve(array $node, array $spec): array
{
    if (! isset($node['$ref'])) {
        return $node;
    }
    $value = $spec;
    foreach (explode('/', substr($node['$ref'], 2)) as $part) {
        $value = $value[str_replace(['~1', '~0'], ['/', '~'], $part)];
    }
    return resolve($value, $spec);
}

function exportedRequest(string $id, string $name, int $expected, ?array $body = null, array $options = []): array
{
    global $operations, $spec;
    [$path, $method, $operation] = $operations[$id];
    $url = '{{base_url}}'.str_replace(['{order}', '{payment}'], ['{{order_id}}', '{{payment_id}}'], $path);
    $request = ['method' => strtoupper($method), 'header' => [['key' => 'Accept', 'value' => $id === 'documentation' ? 'text/html' : 'application/json']],
        'url' => $options['url'] ?? $url, 'description' => $operation['description'] ?? $operation['summary']];
    if (empty($operation['security']) || ($options['anonymous'] ?? false)) {
        $request['auth'] = ['type' => 'noauth'];
    }
    if ($body !== null) {
        $request['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
        $request['body'] = ['mode' => 'raw', 'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'options' => ['raw' => ['language' => 'json']]];
    }
    if ($id === 'processPayment' && ! ($options['missing_key'] ?? false)) {
        $request['header'][] = ['key' => 'Idempotency-Key', 'value' => $options['key'] ?? '{{$guid}}'];
    }
    $responses = [];
    foreach ($operation['responses'] as $status => $response) {
        $response = resolve($response, $spec);
        $media = $response['content']['application/json'] ?? $response['content']['text/html'] ?? [];
        $examples = $media['examples'] ?? [];
        if ($examples === []) {
            $examples = ['Example' => ['value' => $media['example'] ?? $media['schema']['example'] ?? null]];
        }
        foreach ($examples as $label => $example) {
            $value = resolve($example, $spec)['value'] ?? null;
            $headers = [['key' => 'Content-Type', 'value' => isset($response['content']['text/html']) ? 'text/html' : 'application/json']];
            foreach ($response['headers'] ?? [] as $header => $definition) {
                $headers[] = ['key' => $header, 'value' => (string) ($definition['schema']['example'] ?? '1')];
            }
            $responses[] = ['name' => $status.' '.$label, 'originalRequest' => $request,
                'status' => $response['description'], 'code' => (int) $status,
                '_postman_previewlanguage' => isset($response['content']['text/html']) ? 'html' : 'json',
                'header' => $headers, 'cookie' => [], 'body' => $value === null ? '' : (is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
        }
    }
    $assertions = ['pm.test("Expected HTTP '.$expected.'", () => pm.response.to.have.status('.$expected.'));'];
    if (isset($options['code'])) {
        $assertions[] = 'pm.test("Expected error code", () => pm.expect(pm.response.json().code).to.eql("'.$options['code'].'"));';
    }
    if (isset($options['save'])) {
        $assertions[] = 'pm.collectionVariables.set("'.$options['save'].'", pm.response.json().data.id);';
    }
    array_push($assertions, ...($options['tests'] ?? []));
    $test = [
        'if (pm.response.code === 429 && '.$expected.' !== 429 && Number(pm.collectionVariables.get("_rate_retries") || 0) < 3) {',
        '  pm.test("Rate limit response is well formed", () => { pm.expect(pm.response.json().code).to.eql("TOO_MANY_REQUESTS"); pm.expect(pm.response.headers.has("Retry-After")).to.be.true; });',
        '  pm.collectionVariables.set("_rate_retries", Number(pm.collectionVariables.get("_rate_retries") || 0) + 1);',
        '  pm.execution.setNextRequest(pm.info.requestId);',
        '  setTimeout(() => {}, (Number(pm.response.headers.get("Retry-After")) + 1) * 1000);',
        '} else {', '  pm.collectionVariables.unset("_rate_retries");', ...$assertions, '}',
    ];
    $item = ['id' => substr(hash('sha256', $name), 0, 8).'-0000-4000-8000-'.substr(hash('sha256', $name), 8, 12),
        'name' => $name, 'request' => $request, 'response' => $responses,
        'event' => [['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => $test]]]];
    if (isset($options['pre'])) {
        $item['event'][] = ['listen' => 'prerequest', 'script' => ['type' => 'text/javascript', 'exec' => $options['pre']]];
    }
    return $item;
}

$order = ['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com', 'items' => [['product_name' => 'Book', 'quantity' => 2, 'price' => '19.99']]];
$card = ['method' => 'credit_card', 'payment_details' => ['card_number' => '4242424242424242', 'expiry_month' => 12, 'expiry_year' => 2030, 'cvv' => '123', 'card_holder' => 'Jane Doe']];
$decline = $card;
$decline['payment_details']['card_number'] = '4000000000000002';
$providerError = $card;
$providerError['payment_details']['card_number'] = '4000000000000003';
$paypal = ['method' => 'paypal', 'payment_details' => ['email' => 'payer@example.com']];
$collection = ['info' => ['name' => 'Order and Payment Management API',
    'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    'description' => 'Generated from docs/openapi/openapi.json. Run the whole collection in order on a disposable local database. Register creates a unique user; login/order/payment scripts save variables. Simulated payment data only. Replays reuse one fixed key per run. Runner respects Retry-After and retries a throttled request at most three times (allow 90 seconds per script). Saved responses include all examples declared by each OpenAPI operation. See README.md.'],
    'auth' => ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']]],
    'variable' => array_map(
        fn (string $key, string $value): array => ['key' => $key, 'value' => $value, 'type' => 'string'],
        ['base_url', 'token', 'order_id', 'payment_id', 'email', 'password', 'replay_key', 'pending_order_id', 'paypal_order_id', 'disposable_order_id'],
        ['http://localhost:8080', '', '', '', '', 'StrongPass123!', 'demo-fixed-replay-001', '', '', '']
    ),
    'item' => [
        ['name' => 'Authentication', 'item' => [
            exportedRequest('register', 'Register a unique review user', 201, ['name' => 'API Reviewer', 'email' => '{{email}}', 'password' => '{{password}}', 'password_confirmation' => '{{password}}'], ['pre' => [
                'pm.collectionVariables.set("email", "review." + pm.variables.replaceIn("{{$guid}}") + "@example.test");',
                'pm.collectionVariables.set("replay_key", "demo-" + pm.variables.replaceIn("{{$guid}}"));',
                'pm.collectionVariables.unset("_rate_retries");',
            ]]),
            exportedRequest('login', 'Log in and save JWT', 200, ['email' => '{{email}}', 'password' => '{{password}}'], ['tests' => ['pm.collectionVariables.set("token", pm.response.json().data.access_token);']]),
            exportedRequest('currentUser', 'Read current user', 200),
            exportedRequest('currentUser', 'Missing token returns 401', 401, options: ['anonymous' => true, 'code' => 'UNAUTHENTICATED']),
        ]],
        ['name' => 'Orders', 'item' => [
            exportedRequest('createOrder', 'Create main order and save id', 201, $order, ['save' => 'order_id']),
            exportedRequest('createOrder', 'Create pending order for conflict example', 201, $order, ['save' => 'pending_order_id']),
            exportedRequest('createOrder', 'Create disposable unpaid order', 201, $order, ['save' => 'disposable_order_id']),
            exportedRequest('deleteOrder', 'Delete an unpaid order', 204, options: ['url' => '{{base_url}}/api/orders/{{disposable_order_id}}']),
            exportedRequest('listOrders', 'List orders with pagination', 200, options: ['url' => '{{base_url}}/api/orders?status=pending&per_page=2']),
            exportedRequest('showOrder', 'Read main order', 200),
            exportedRequest('replaceOrder', 'Update customer with PUT', 200, ['customer_name' => 'Updated customer']),
            exportedRequest('updateOrder', 'Replace items with PATCH', 200, ['items' => $order['items']]),
            exportedRequest('changeOrderStatus', 'Invalid order transition returns 409', 409, ['status' => 'pending'], ['code' => 'INVALID_STATUS_TRANSITION']),
            exportedRequest('changeOrderStatus', 'Confirm main order', 200, ['status' => 'confirmed']),
            exportedRequest('createOrder', 'Create PayPal order', 201, $order, ['save' => 'paypal_order_id']),
            exportedRequest('changeOrderStatus', 'Confirm PayPal order', 200, ['status' => 'confirmed'], ['url' => '{{base_url}}/api/orders/{{paypal_order_id}}/status']),
        ]],
        ['name' => 'Payments', 'item' => [
            exportedRequest('processPayment', 'Missing idempotency key returns 422', 422, $card, ['missing_key' => true, 'code' => 'IDEMPOTENCY_KEY_REQUIRED']),
            exportedRequest('processPayment', 'Unsupported gateway returns 422', 422, ['method' => 'unknown', 'payment_details' => ['email' => 'payer@example.com']], ['code' => 'VALIDATION_ERROR']),
            exportedRequest('processPayment', 'Unconfirmed order returns 409', 409, $card, ['url' => '{{base_url}}/api/orders/{{pending_order_id}}/payments', 'code' => 'ORDER_NOT_CONFIRMED']),
            exportedRequest('processPayment', 'Card decline is a persisted failed payment', 201, $decline, ['tests' => ['pm.test("Card declined", () => pm.expect(pm.response.json().data.failure_code).to.eql("CARD_DECLINED"));']]),
            exportedRequest('processPayment', 'Provider error returns a clean failed payment', 201, $providerError, ['tests' => ['pm.test("Provider error normalized", () => pm.expect(pm.response.json().data.failure_code).to.eql("GATEWAY_ERROR"));']]),
            exportedRequest('processPayment', 'Successful card payment with fixed replay key', 201, $card, ['key' => '{{replay_key}}', 'save' => 'payment_id', 'tests' => ['pm.test("Payment succeeded", () => pm.expect(pm.response.json().data.status).to.eql("successful"));', 'pm.collectionVariables.set("_first_payment_body", pm.response.text());']]),
            exportedRequest('processPayment', 'Replay the same fixed key', 201, $card, ['key' => '{{replay_key}}', 'tests' => ['pm.test("Replay header", () => pm.expect(pm.response.headers.get("Idempotent-Replayed")).to.eql("true"));', 'pm.test("Exact original response", () => pm.expect(pm.response.text()).to.eql(pm.collectionVariables.get("_first_payment_body")));']]),
            exportedRequest('processPayment', 'Changed body with same key returns 422', 422, $paypal, ['key' => '{{replay_key}}', 'code' => 'IDEMPOTENCY_KEY_REUSED']),
            exportedRequest('processPayment', 'Already paid order returns 409', 409, $card, ['code' => 'ORDER_ALREADY_PAID']),
            exportedRequest('processPayment', 'Successful PayPal payment', 201, $paypal, ['url' => '{{base_url}}/api/orders/{{paypal_order_id}}/payments', 'tests' => ['pm.test("PayPal succeeded", () => pm.expect(pm.response.json().data.status).to.eql("successful"));']]),
            exportedRequest('listPayments', 'List and filter payments', 200, options: ['url' => '{{base_url}}/api/payments?method=credit_card&per_page=2']),
            exportedRequest('listOrderPayments', 'List main order attempts', 200),
            exportedRequest('showPayment', 'Read successful payment', 200),
            exportedRequest('deleteOrder', 'Orders with payments cannot be deleted', 409, options: ['code' => 'ORDER_HAS_PAYMENTS']),
            exportedRequest('changeOrderStatus', 'Paid order cannot be cancelled', 409, ['status' => 'cancelled'], ['code' => 'ORDER_HAS_PAYMENTS']),
        ]],
        ['name' => 'Ledger', 'item' => [exportedRequest('orderLedger', 'Read order ledger', 200), exportedRequest('paymentLedger', 'Read payment ledger', 200)]],
        ['name' => 'Documentation', 'item' => [exportedRequest('documentation', 'Open Swagger UI', 200)]],
        ['name' => 'Authentication cleanup', 'item' => [exportedRequest('logout', 'Log out and invalidate token', 204)]],
    ],
];
$directory = $root.'/docs/postman';
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    throw new RuntimeException('Could not create Postman export directory.');
}
file_put_contents($directory.'/collection.json', json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
file_put_contents($directory.'/local.environment.json', json_encode(['name' => 'Order Payment local', 'values' => [
    ['key' => 'base_url', 'value' => 'http://localhost:8080', 'enabled' => true],
], '_postman_variable_scope' => 'environment'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo "Postman collection and local environment exported from OpenAPI.\n";
