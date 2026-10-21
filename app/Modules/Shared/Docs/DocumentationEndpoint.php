<?php

declare(strict_types=1);

namespace App\Modules\Shared\Docs;

use OpenApi\Attributes as OA;

final class DocumentationEndpoint
{
    #[OA\Get(
        path: '/api/documentation',
        operationId: 'documentation',
        summary: 'Open Swagger UI',
        description: 'Public when API_DOCUMENTATION_ENABLED is true. Disabled in production by default. Documentation assets and spec downloads use the same flag and IP rate limiter.',
        tags: ['Documentation'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Swagger UI.', content: new OA\MediaType(mediaType: 'text/html', schema: new OA\Schema(type: 'string', example: '<!doctype html><html><body>Swagger UI</body></html>'))),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function show(): void
    {
    }
}
