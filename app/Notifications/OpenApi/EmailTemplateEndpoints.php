<?php

namespace App\Notifications\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'EmailTemplate', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'organization_id', type: 'integer', readOnly: true),
    new OA\Property(property: 'type', type: 'string', enum: ['account.created', 'event.attached', 'payment.confirmed']),
    new OA\Property(property: 'subject', type: 'string', maxLength: 255),
    new OA\Property(property: 'body', type: 'string', maxLength: 20000),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
])]
class EmailTemplateEndpoints
{
    #[OA\Get(path: '/email-templates', summary: 'List organization overrides; requires email_templates.view or email_templates.manage. Missing types use defaults.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        responses: [new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EmailTemplate'))])), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function index(): void {}

    #[OA\Get(path: '/email-templates/types', summary: 'List supported types, variables, default subject and body. Requires email_templates.view or email_templates.manage.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        responses: [new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [new OA\Property(property: 'type', type: 'string'), new OA\Property(property: 'subject', type: 'string'), new OA\Property(property: 'body', type: 'string'), new OA\Property(property: 'variables', type: 'array', items: new OA\Items(type: 'string'))]))])), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function types(): void {}

    #[OA\Post(path: '/email-templates', summary: 'Create one override per organization/type. Requires email_templates.manage. Plain text only; account.created body must contain {{setup_url}}.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['type', 'subject', 'body'], properties: [
            new OA\Property(property: 'type', type: 'string', enum: ['account.created', 'event.attached', 'payment.confirmed']),
            new OA\Property(property: 'subject', type: 'string', maxLength: 255), new OA\Property(property: 'body', type: 'string', maxLength: 20000),
        ])),
        responses: [new OA\Response(response: 201, description: 'Success', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EmailTemplate')])), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function store(): void {}

    #[OA\Get(path: '/email-templates/{email_template}', summary: 'Read an organization template. Requires email_templates.view or email_templates.manage.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        parameters: [new OA\PathParameter(name: 'email_template', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EmailTemplate')])), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function show(): void {}

    #[OA\Put(path: '/email-templates/{email_template}', summary: 'Update supplied subject/body; type is immutable. Requires email_templates.manage.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        parameters: [new OA\PathParameter(name: 'email_template', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: [], properties: [
            new OA\Property(property: 'subject', type: 'string', maxLength: 255), new OA\Property(property: 'body', type: 'string', maxLength: 20000),
        ])),
        responses: [new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EmailTemplate')])), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function update(): void {}

    #[OA\Patch(path: '/email-templates/{email_template}', summary: 'Partially update subject/body. Requires email_templates.manage.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        parameters: [new OA\PathParameter(name: 'email_template', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: [], properties: [
            new OA\Property(property: 'subject', type: 'string', maxLength: 255), new OA\Property(property: 'body', type: 'string', maxLength: 20000),
        ])),
        responses: [new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EmailTemplate')])), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function patch(): void {}

    #[OA\Delete(path: '/email-templates/{email_template}', summary: 'Delete override and restore default content. Requires email_templates.manage.', security: [['bearerAuth' => []]], tags: ['Email Templates'],
        parameters: [new OA\PathParameter(name: 'email_template', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Success'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Template not found in organization'), new OA\Response(response: 422, description: 'Invalid fields, duplicate type or unsupported placeholders')],
    )]
    public function destroy(): void {}
}
