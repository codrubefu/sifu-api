<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

class GradeApiEndpoints
{
    #[OA\Get(path: '/grades', summary: 'List organization grades', security: [['bearerAuth' => []]], tags: ['Grades'], responses: [new OA\Response(response: 200, description: 'Paginated grades.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Grade'))], type: 'object')), new OA\Response(response: 403, description: 'Missing grades.view right.')])]
    public function index(): void {}

    #[OA\Post(path: '/grades', summary: 'Create an organization grade', security: [['bearerAuth' => []]], tags: ['Grades'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'description', type: 'string', nullable: true), new OA\Property(property: 'is_active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 201, description: 'Grade created.', content: new OA\JsonContent(ref: '#/components/schemas/Grade')), new OA\Response(response: 422, description: 'Validation failed.')])]
    public function store(): void {}

    #[OA\Get(path: '/grades/{grade}', summary: 'Get an organization grade', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'grade', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Grade.', content: new OA\JsonContent(ref: '#/components/schemas/Grade')), new OA\Response(response: 404, description: 'Grade not found.')])]
    public function show(): void {}

    #[OA\Patch(path: '/grades/{grade}', summary: 'Update an organization grade', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'grade', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Grade updated.', content: new OA\JsonContent(ref: '#/components/schemas/Grade'))])]
    public function update(): void {}

    #[OA\Delete(path: '/grades/{grade}', summary: 'Soft-delete an organization grade', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'grade', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 204, description: 'Grade deleted; user history is preserved.')])]
    public function destroy(): void {}

    #[OA\Get(path: '/grades/{grade}/users', summary: 'List users whose active grade is selected grade', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'grade', required: true, schema: new OA\Schema(type: 'integer')), new OA\QueryParameter(name: 'search', schema: new OA\Schema(type: 'string')), new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer')), new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Paginated users with this active grade.')])]
    public function users(): void {}

    #[OA\Get(path: '/users/{user}/grades', summary: 'List a user grade history', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Paginated user grade history.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/UserGrade'))], type: 'object'))])]
    public function userIndex(): void {}

    #[OA\Post(path: '/users/{user}/grades', summary: 'Award a grade to a user', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 201, description: 'Grade awarded.')])]
    public function userStore(): void {}

    #[OA\Patch(path: '/users/{user}/grades/{userGrade}', summary: 'Update a user grade award', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')), new OA\PathParameter(name: 'userGrade', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Award updated.')])]
    public function userUpdate(): void {}

    #[OA\Delete(path: '/users/{user}/grades/{userGrade}', summary: 'Soft-delete a user grade award', security: [['bearerAuth' => []]], tags: ['Grades'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')), new OA\PathParameter(name: 'userGrade', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 204, description: 'Award deleted.')])]
    public function userDestroy(): void {}
}
