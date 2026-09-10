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

    #[OA\Post(
        path: '/check-ins/search',
        summary: 'Search member for rapid check-in',
        security: [['bearerAuth' => []]],
        tags: ['Check-ins'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['query'], properties: [
            new OA\Property(property: 'query', type: 'string'),
            new OA\Property(property: 'occurrence_id', type: 'integer', nullable: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Check-in verdict.')]
    )]
    public function search(SearchCheckInMemberRequest $request): JsonResponse
    {
        $occurrence = $request->integer('occurrence_id') > 0
            ? EventOccurrence::query()->findOrFail($request->integer('occurrence_id'))
            : null;

        return response()->json([
            'success' => true,
            'data' => new CheckInResource($this->checkIns->search($request->string('query')->toString(), $occurrence)),
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
