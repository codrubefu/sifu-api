<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

class SmtpSettingApiEndpoints
{
    #[OA\Get(path: '/smtp-settings', summary: 'Get the organization outgoing mail (SMTP) settings', security: [['bearerAuth' => []]], tags: ['SMTP Settings'], responses: [new OA\Response(response: 200, description: 'SMTP settings.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/SmtpSetting')], type: 'object')), new OA\Response(response: 403, description: 'Missing smtp_settings.view right.'), new OA\Response(response: 404, description: 'No SMTP settings configured for this organization.')])]
    public function show(): void {}

    #[OA\Post(path: '/smtp-settings', summary: 'Create the organization outgoing mail (SMTP) settings', security: [['bearerAuth' => []]], tags: ['SMTP Settings'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['host', 'port', 'from_address'], properties: [new OA\Property(property: 'host', type: 'string'), new OA\Property(property: 'port', type: 'integer'), new OA\Property(property: 'username', type: 'string', nullable: true), new OA\Property(property: 'password', type: 'string', nullable: true), new OA\Property(property: 'encryption', type: 'string', nullable: true, enum: ['tls', 'ssl']), new OA\Property(property: 'from_address', type: 'string', format: 'email'), new OA\Property(property: 'from_name', type: 'string', nullable: true), new OA\Property(property: 'active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 201, description: 'SMTP settings created.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/SmtpSetting')], type: 'object')), new OA\Response(response: 422, description: 'Validation failed, or settings already exist for this organization.')])]
    public function store(): void {}

    #[OA\Patch(path: '/smtp-settings', summary: 'Update the organization outgoing mail (SMTP) settings', security: [['bearerAuth' => []]], tags: ['SMTP Settings'], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'host', type: 'string'), new OA\Property(property: 'port', type: 'integer'), new OA\Property(property: 'username', type: 'string', nullable: true), new OA\Property(property: 'password', type: 'string', nullable: true, description: 'Leave blank to keep the current password.'), new OA\Property(property: 'encryption', type: 'string', nullable: true, enum: ['tls', 'ssl']), new OA\Property(property: 'from_address', type: 'string', format: 'email'), new OA\Property(property: 'from_name', type: 'string', nullable: true), new OA\Property(property: 'active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 200, description: 'SMTP settings updated.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/SmtpSetting')], type: 'object')), new OA\Response(response: 404, description: 'No SMTP settings configured for this organization.')])]
    public function update(): void {}

    #[OA\Delete(path: '/smtp-settings', summary: 'Delete the organization outgoing mail (SMTP) settings', security: [['bearerAuth' => []]], tags: ['SMTP Settings'], responses: [new OA\Response(response: 204, description: 'SMTP settings deleted; the organization falls back to the system default mailer.'), new OA\Response(response: 404, description: 'No SMTP settings configured for this organization.')])]
    public function destroy(): void {}
}
