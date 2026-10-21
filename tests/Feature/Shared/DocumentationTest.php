<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class DocumentationTest extends TestCase
{
    public function test_every_api_operation_is_valid_documented_rate_limited_and_secured(): void
    {
        $openApi = (new \OpenApi\Generator())->setVersion('3.0.0')->generate([app_path('Modules')]);
        $this->assertNotNull($openApi);
        $this->assertTrue($openApi->validate());
        $spec = json_decode($openApi->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $seen = 0;
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertNotEmpty(array_filter($middleware, fn (string $name): bool => str_starts_with($name, 'throttle:')), $route->uri().' has no rate limiter.');
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $operation = $spec['paths']['/'.$route->uri()][strtolower($method)] ?? null;
                $this->assertNotNull($operation, $method.' '.$route->uri().' is undocumented.');
                $this->assertNotEmpty($operation['tags'] ?? [], $route->uri());
                $this->assertNotEmpty($operation['summary'] ?? '', $route->uri());
                $statuses = array_map('strval', array_keys($operation['responses']));
                $this->assertNotEmpty(array_filter($statuses, fn (string $status): bool => str_starts_with($status, '2')));
                $this->assertNotEmpty(array_filter($statuses, fn (string $status): bool => str_starts_with($status, '4')));
                if (isset($operation['requestBody'])) {
                    $media = $operation['requestBody']['content']['application/json'];
                    $this->assertTrue(isset($media['example']) || ! empty($media['examples']), $route->uri().' needs a request example.');
                }
                foreach ($operation['responses'] as $status => $response) {
                    if (isset($response['$ref'])) {
                        $response = $spec['components']['responses'][basename($response['$ref'])];
                    }
                    foreach ($response['content'] ?? [] as $media) {
                        $this->assertTrue(isset($media['example']) || ! empty($media['examples']) || isset($media['schema']['example']), $method.' '.$route->uri().' response '.$status.' needs an example.');
                    }
                }
                if (in_array('auth:api', $middleware, true)) {
                    $this->assertContains(['bearerAuth' => []], $operation['security'] ?? []);
                }
                $seen++;
            }
        }
        $this->assertGreaterThanOrEqual(18, $seen);
        $post = $spec['paths']['/api/orders/{order}/payments']['post'];
        $this->assertContains('#/components/parameters/IdempotencyKey', array_column($post['parameters'], '$ref'));
        $this->assertArrayHasKey('FailedPayment', $post['responses']['201']['content']['application/json']['examples']);
        $this->assertArrayHasKey('IdempotentReplay', $post['responses']['201']['content']['application/json']['examples']);
    }

    public function test_swagger_ui_and_generated_spec_are_available(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();
        $this->get('/api/documentation')->assertOk()->assertSee('SwaggerUIBundle', false);
        $this->get('/documentation/spec')->assertOk();

        $json = json_decode(file_get_contents(storage_path('api-docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $yaml = Yaml::parseFile(storage_path('api-docs/openapi.yaml'));

        $this->assertSame('3.0.0', $json['openapi']);
        $this->assertSame($json, $yaml);
        foreach ([
            '/api/auth/register' => 'post', '/api/auth/login' => 'post',
            '/api/auth/logout' => 'post', '/api/auth/me' => 'get',
        ] as $path => $method) {
            $this->assertSame(['Authentication'], $json['paths'][$path][$method]['tags']);
            $this->assertArrayHasKey('429', $json['paths'][$path][$method]['responses']);
        }
        $this->assertSame([['bearerAuth' => []]], $json['paths']['/api/auth/me']['get']['security']);
    }

    public function test_documentation_flag_controls_ui_and_spec_access(): void
    {
        config(['documentation.enabled' => false]);

        $this->get('/api/documentation')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->get('/documentation/spec')->assertNotFound();
        $this->get('/documentation/spec/asset/swagger-ui.css')->assertNotFound();
    }

    public function test_documentation_is_rate_limited(): void
    {
        config(['rate_limits.documentation_per_minute' => 1]);

        $this->get('/api/documentation')->assertOk();
        $this->getJson('/documentation/spec')->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')->assertHeader('Retry-After');
    }

    public function test_delivery_exports_match_the_api_and_postman_covers_every_operation(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();
        $spec = json_decode(file_get_contents(storage_path('api-docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($spec, json_decode(file_get_contents(base_path('docs/openapi/openapi.json')), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame($spec, Yaml::parseFile(base_path('docs/openapi/openapi.yaml')));
        $collectionPath = base_path('docs/postman/collection.json');
        $before = hash_file('sha256', $collectionPath);
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('scripts/export-postman.php')], base_path());
        $process->mustRun();
        $this->assertSame($before, hash_file('sha256', $collectionPath), 'Postman exports must be deterministic and current.');
        $collection = json_decode(file_get_contents($collectionPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $collection['info']['schema']);
        $variables = array_column($collection['variable'], 'value', 'key');
        $this->assertSame('', $variables['token']);
        foreach (['base_url', 'token', 'order_id', 'payment_id', 'replay_key'] as $key) {
            $this->assertArrayHasKey($key, $variables);
        }
        $covered = [];
        $savedStatuses = [];
        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] as $item) {
                $url = explode('?', $item['request']['url'])[0];
                $url = str_replace('{{base_url}}', '', $url);
                $url = preg_replace('/\{\{[a-z_]*order_id\}\}/', '{order}', $url);
                $url = str_replace('{{payment_id}}', '{payment}', $url);
                $covered[$item['request']['method'].' '.$url] = true;
                $this->assertNotEmpty($item['response'], $item['name']);
                $this->assertNotEmpty(array_filter($item['response'], fn (array $response): bool => $response['code'] >= 200 && $response['code'] < 300), $item['name']);
                $this->assertNotEmpty(array_filter($item['response'], fn (array $response): bool => $response['code'] >= 400 && $response['code'] < 500), $item['name']);
                foreach ($item['response'] as $response) {
                    $savedStatuses[$response['code']] = true;
                }
            }
        }
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $this->assertArrayHasKey(strtoupper($method).' '.$path, $covered);
            }
        }
        foreach ([401, 403, 404, 409, 422, 429] as $status) {
            $this->assertArrayHasKey($status, $savedStatuses);
        }
    }
}
