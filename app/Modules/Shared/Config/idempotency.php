<?php

declare(strict_types=1);
return ['ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24)];
