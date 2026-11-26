<?php

namespace App\CheckIns\Http\Controllers\Api;

use App\CheckIns\Http\Requests\ConfirmCheckInRequest;
use App\CheckIns\Http\Requests\SearchCheckInMemberRequest;
use App\CheckIns\Http\Resources\CheckInResource;
use App\CheckIns\Services\CheckInService;
use App\Events\Http\Resources\EventOccurrenceResource;
use App\Events\Models\EventOccurrence;
use App\Users\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkIns) {}

    #[OA\Get(
        path: '/check-ins/occurrences/current',
        summary: 'List current check-in occurrences',
        security: [['bearerAuth' => []]],
        tags: ['Check-ins'],
        responses: [new OA\Response(response: 200, description: 'Current occurrences.')]
    )]
    public function currentOccurrences(Request $request): AnonymousResourceCollection
    {
        return EventOccurrenceResource::collection($this->checkIns->currentOccurrences($request->user()));
    }

    #[OA\Get(
        path: '/check-ins/recent',
        summary: 'List the latest attended check-ins (max 10)',
        security: [['bearerAuth' => []]],
        tags: ['Check-ins'],
        parameters: [new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 10))],
        responses: [new OA\Response(response: 200, description: 'Latest check-ins, newest first.')]
    )]
    public function recent(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', CheckInService::MAX_RECENT);

        return response()->json([
            'success' => true,
            'data' => $this->checkIns->recent($limit > 0 ? $limit : CheckInService::MAX_RECENT),
        ]);
    }

    #[OA\Post(
        path: '/check-ins/search',
        summary: 'Search member for rapid check-in',
        security: [['bearerAuth' => []]],
        tags: ['Check-ins'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['query'], properties: [
            new OA\Property(property: 'query', type: 'string', description: 'Card code, phone, email, or first/last name (any order).'),
            new OA\Property(property: 'user_id', type: 'integer', nullable: true, description: 'Pick a member from a previous multiple_matches candidate list.'),
            new OA\Property(property: 'occurrence_id', type: 'integer', nullable: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Check-in verdict, or verdict=multiple_matches with candidates. `missing_service_groups` (int[]) lists the unmet event service_groups indices when reason=missing_required_service, otherwise [].')]
    )]
    public function search(SearchCheckInMemberRequest $request): JsonResponse
    {
        $occurrence = $request->integer('occurrence_id') > 0
            ? EventOccurrence::query()->findOrFail($request->integer('occurrence_id'))
            : null;

        return response()->json([
            'success' => true,
            'data' => new CheckInResource($this->checkIns->search(
                $request->string('query')->toString(),
                $occurrence,
                $request->integer('user_id') > 0 ? $request->integer('user_id') : null,
            )),
        ]);
    }

    #[OA\Post(
        path: '/check-ins/confirm',
        summary: 'Confirm rapid check-in',
        security: [['bearerAuth' => []]],
        tags: ['Check-ins'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['user_id', 'occurrence_id'], properties: [
            new OA\Property(property: 'user_id', type: 'integer'),
            new OA\Property(property: 'occurrence_id', type: 'integer'),
            new OA\Property(property: 'allow_override', type: 'boolean'),
            new OA\Property(property: 'notes', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Check-in accepted or already present.')]
    )]
    public function confirm(ConfirmCheckInRequest $request): JsonResponse
    {
        $data = $request->validated();
        $member = User::query()->findOrFail($data['user_id']);
        $occurrence = EventOccurrence::query()->findOrFail($data['occurrence_id']);

        return response()->json([
            'success' => true,
            'data' => new CheckInResource($this->checkIns->confirm(
                $member,
                $occurrence,
                $request->user(),
                (bool) ($data['allow_override'] ?? false),
                $data['notes'] ?? null,
            )),
        ]);
    }
}
