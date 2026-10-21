<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

final class ApplicationHealthTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertJsonPath('name', config('app.name'))
            ->assertJsonPath('status', 'ok');
    }
}
