<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'ERP Laravel API Documentation',
    description: 'API documentation for bearer-token authenticated ERP endpoints.',
)]
#[OA\Server(
    url: '/api',
    description: 'API Server',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
class Documentation
{
}
