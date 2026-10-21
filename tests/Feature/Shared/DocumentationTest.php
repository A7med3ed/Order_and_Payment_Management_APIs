<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class DocumentationTest extends TestCase
{
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
}
