<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Application;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function resetAuthentication(): void
    {
        $this->app['auth']->guard('api')->unsetToken();
        $this->app['auth']->forgetGuards();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if ($app->configurationIsCached()
            || $app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.database') !== 'app_testing') {
            throw new RuntimeException('Tests require uncached configuration and the dedicated MySQL app_testing database.');
        }

        return $app;
    }
}
