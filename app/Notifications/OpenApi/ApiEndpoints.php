<?php

namespace App\Notifications\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Put(path: '/notification-preferences', summary: 'Subscribe or unsubscribe on a channel and scope', description: 'The all scope applies to every notification in that channel; campaigns can be controlled with the campaigns scope.', security: [['bearerAuth' => []]], tags: ['Notification Preferences'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['channel', 'scope', 'subscribed'], properties: [new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'mail', 'push']), new OA\Property(property: 'scope', type: 'string', example: 'campaigns'), new OA\Property(property: 'subscribed', type: 'boolean', example: false)], type: 'object')), responses: [new OA\Response(response: 200, description: 'Preference saved.'), new OA\Response(response: 422, description: 'Validation failed.')])]
    public function preference(): void {}

    #[OA\Post(path: '/push-devices', summary: 'Register or refresh a push device token', security: [['bearerAuth' => []]], tags: ['Notification Preferences'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token'], properties: [new OA\Property(property: 'token', type: 'string', maxLength: 2048), new OA\Property(property: 'device_id', type: 'string', nullable: true, maxLength: 255)], type: 'object')), responses: [new OA\Response(response: 201, description: 'Device registered.'), new OA\Response(response: 422, description: 'Validation failed.')])]
    public function registerDevice(): void {}

    #[OA\Delete(path: '/push-devices/{device}', summary: 'Remove one of the authenticated user push devices', security: [['bearerAuth' => []]], tags: ['Notification Preferences'], parameters: [new OA\PathParameter(name: 'device', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 204, description: 'Device removed.'), new OA\Response(response: 404, description: 'Device does not belong to the authenticated user.')])]
    public function removeDevice(): void {}
}
