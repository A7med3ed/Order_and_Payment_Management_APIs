<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$environment = file_get_contents($root . '/.env');

if ($environment === false) {
    throw new RuntimeException('Create .env before generating the application key.');
}

if (preg_match('/^APP_KEY=.+$/m', $environment) !== 1) {
    $command = new Process([PHP_BINARY, 'artisan', 'key:generate', '--no-interaction'], $root);

    exit($command->run(static function (string $type, string $output): void {
        echo $output;
    }));
}
