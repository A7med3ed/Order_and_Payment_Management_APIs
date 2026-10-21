<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDocumentationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('documentation.enabled'), 404);

        return $next($request);
    }
}
