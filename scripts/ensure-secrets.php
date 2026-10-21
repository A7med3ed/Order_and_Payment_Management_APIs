<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$environment = file_get_contents($root . '/.env');

if ($environment === false) {
    throw new RuntimeException('Create .env before generating application secrets.');
}

$values = Dotenv::parse($environment);

foreach (['APP_KEY' => ['key:generate'], 'JWT_SECRET' => ['jwt:secret', '--force']] as $name => $arguments) {
    if (trim($values[$name] ?? '') !== '') {
        continue;
    }

    $command = new Process([PHP_BINARY, 'artisan', ...$arguments, '--no-interaction'], $root);
    $exitCode = $command->run(static function (string $type, string $output): void {
        echo $output;
    });

    if ($exitCode !== 0) {
        exit($exitCode);
    }
}
