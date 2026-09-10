<?php

namespace App\Campaigns\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(path: '/campaigns', summary: 'List campaigns in the authenticated organization', security: [['bearerAuth' => []]], tags: ['Campaigns'], responses: [new OA\Response(response: 200, description: 'Campaign list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Campaign'))], type: 'object')), new OA\Response(response: 401, description: 'Unauthenticated.')])]
    public function index(): void {}

    #[OA\Post(path: '/campaigns', summary: 'Create a draft campaign', security: [['bearerAuth' => []]], tags: ['Campaigns'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SaveCampaignRequest')), responses: [new OA\Response(response: 201, description: 'Draft created.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Campaign')], type: 'object')), new OA\Response(response: 422, description: 'Validation failed, including a segment from another organization.')])]
    public function store(): void {}

    #[OA\Patch(path: '/campaigns/{campaign}', summary: 'Update a draft campaign', security: [['bearerAuth' => []]], tags: ['Campaigns'], parameters: [new OA\PathParameter(name: 'campaign', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SaveCampaignRequest')), responses: [new OA\Response(response: 200, description: 'Draft updated.'), new OA\Response(response: 404, description: 'Campaign is outside the organization.'), new OA\Response(response: 409, description: 'Campaign is no longer a draft.'), new OA\Response(response: 422, description: 'Validation failed.')])]
    public function update(): void {}

    #[OA\Get(path: '/campaigns/{campaign}/preview', summary: 'Preview the current dynamic recipient audience', description: 'Membership is evaluated from current segment data and is limited to 100 preview rows; count contains the complete audience size.', security: [['bearerAuth' => []]], tags: ['Campaigns'], parameters: [new OA\PathParameter(name: 'campaign', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Recipient preview.', content: new OA\JsonContent(properties: [new OA\Property(property: 'count', type: 'integer'), new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'))], type: 'object')), new OA\Response(response: 404, description: 'Campaign is outside the organization.')])]
    public function preview(): void {}

    #[OA\Post(path: '/campaigns/{campaign}/schedule', summary: 'Schedule a draft campaign', description: 'The dynamic audience is evaluated when the scheduled campaign is dispatched, not frozen now.', security: [['bearerAuth' => []]], tags: ['Campaigns'], parameters: [new OA\PathParameter(name: 'campaign', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['scheduled_at'], properties: [new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time')], type: 'object')), responses: [new OA\Response(response: 200, description: 'Campaign scheduled.'), new OA\Response(response: 409, description: 'Campaign is not a draft.'), new OA\Response(response: 422, description: 'Invalid schedule.')])]
    public function schedule(): void {}

    #[OA\Post(path: '/campaigns/{campaign}/cancel', summary: 'Cancel a draft or scheduled campaign', security: [['bearerAuth' => []]], tags: ['Campaigns'], parameters: [new OA\PathParameter(name: 'campaign', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Campaign cancelled.'), new OA\Response(response: 409, description: 'Campaign has already been sent or cancelled.')])]
    public function cancel(): void {}

    #[OA\Get(path: '/campaigns/{campaign}/statistics', summary: 'Get aggregated delivery statistics', security: [['bearerAuth' => []]], tags: ['Campaigns'], parameters: [new OA\PathParameter(name: 'campaign', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Aggregated statuses.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CampaignStatistics')], type: 'object')), new OA\Response(response: 404, description: 'Campaign is outside the organization.')])]
    public function statistics(): void {}
}
