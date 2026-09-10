<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Grade',
    required: ['id', 'name', 'is_active'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'organization_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Centura Neagra'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Nivel avansat.'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'users_count', type: 'integer', example: 12),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserGrade',
    required: ['id', 'user_id', 'grade_id', 'obtained_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 10),
        new OA\Property(property: 'user_id', type: 'integer', example: 35),
        new OA\Property(property: 'grade_id', type: 'integer', example: 2),
        new OA\Property(property: 'grade', ref: '#/components/schemas/Grade'),
        new OA\Property(property: 'obtained_at', type: 'string', format: 'date', example: '2026-08-20'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Examen promovat.'),
        new OA\Property(property: 'created_by', type: 'integer', nullable: true, example: 1),
    ],
    type: 'object',
)]
class GradeSchemas
{
}
