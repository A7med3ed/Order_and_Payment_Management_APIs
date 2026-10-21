<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\DTOs;

final readonly class GatewayConfigData
{
    public function __construct(
        public string $method,
        public bool $enabled,
        #[\SensitiveParameter] public array $credentials = [],
        public array $options = [],
    ) {
    }
}
