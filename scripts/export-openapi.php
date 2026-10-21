<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$destination = $root . '/docs/openapi';

if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
    throw new RuntimeException('Could not create the OpenAPI export directory.');
}

foreach (['json', 'yaml'] as $format) {
    if (! copy($root . '/storage/api-docs/openapi.' . $format, $destination . '/openapi.' . $format)) {
        throw new RuntimeException('Could not export OpenAPI ' . $format . '.');
    }
}

echo "OpenAPI JSON and YAML exported to docs/openapi.\n";
