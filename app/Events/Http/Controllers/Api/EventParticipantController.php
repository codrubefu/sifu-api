<?php

namespace App\Events\Http\Controllers\Api;

use App\Events\Http\Requests\AddEventParticipantRequest;
use App\Events\Http\Requests\BulkAddEventParticipantsRequest;
use App\Events\Http\Requests\UpdateEventParticipantRequest;
use App\Events\Http\Resources\EventParticipantResource;
use App\Events\Models\EventOccurrence;
use App\Events\Services\EventEligibilityService;
use App\Events\Services\OccurrenceAttendancePdfService;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Http\Controllers\Controller;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class EventParticipantController extends Controller
{
    public function __construct(
        private readonly EventEligibilityService $eligibility,
        private readonly ServiceLifecycleService $serviceLifecycle,
    )
    {
    }

    #[OA\Get(
        path: '/event-occurrences/{occurrence}/participants',
        summary: 'List occurrence participants',
        description: 'Returns paginated users registered for a concrete event occurrence.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'sort', required: false, schema: new OA\Schema(type: 'string', example: 'last_name')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Participants list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventParticipant'))])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function index(EventOccurrence $occurrence): AnonymousResourceCollection
    {
        return EventParticipantResource::collection(
            $occurrence->participants()->orderBy('last_name')->orderBy('first_name')->paginate(request()->integer('per_page', 15))
        );
    }

    public function downloadPdf(Request $request, EventOccurrence $occurrence, OccurrenceAttendancePdfService $pdf): Response
    {
        abort_unless((int) $occurrence->organization_id === (int) $request->user()->organization_id, 404);

        return $pdf->download($occurrence);
    }

    #[OA\Get(
        path: '/event-occurrences/{occurrence}/eligible-participants',
        summary: 'List users eligible for quick add',
        description: 'Returns users that can be added to a concrete event occurrence: visible in the current organization, not already participants, and matching the active-service requirement when the event requires one.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eligible users list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventEligibleParticipant'))])),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function eligible(Request $request, EventOccurrence $occurrence): AnonymousResourceCollection
    {
        $occurrence->load('event.requiredService');
        $event = $occurrence->event;

        $users = User::query()
            ->with('activeServices')
            ->whereDoesntHave('eventOccurrences', fn ($query) => $query->whereKey($occurrence->id))
            ->when($event?->requires_active_service, function ($query) use ($event): void {
                $query->whereHas('activeServices', function ($query) use ($event): void {
                    if ($event->required_service_id !== null) {
                        $query->where('services.id', $event->required_service_id);
                    }
                });
            })
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('user_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    #[OA\Post(
        path: '/event-occurrences/{occurrence}/participants',
        summary: 'Add occurrence participant',
        description: 'Adds a user to an occurrence after duplicate, capacity, and active-service eligibility checks. When the event requires a limited service, one access is consumed atomically from the user service assignment.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AddEventParticipantRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Success.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Participant added.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventParticipant')])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(AddEventParticipantRequest $request, EventOccurrence $occurrence): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->findOrFail($data['user_id']);
        $occurrence->load('event.requiredService');

        if ($occurrence->participants()->whereKey($user->id)->exists()) {
            return $this->error('User is already registered for this occurrence.', 422);
        }

        if (! $this->eligibility->canUserJoinOccurrence($user, $occurrence)) {
            return $this->error('User does not have the required active service.', 403);
        }

        $maxParticipants = $occurrence->event->max_participants;
        if ($maxParticipants !== null && $occurrence->activeParticipants()->count() >= $maxParticipants) {
            return $this->error('Event occurrence has reached the maximum number of participants.', 400);
        }

        DB::transaction(function () use ($occurrence, $user, $data): void {
            $this->serviceLifecycle->consumeEventAccess($user, $occurrence->event);
            $occurrence->participants()->attach($user->id, [
                'status' => $data['status'] ?? 'registered',
                'registered_at' => $data['registered_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $participant = $occurrence->participants()->whereKey($user->id)->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Participant added successfully.',
            'data' => new EventParticipantResource($participant),
        ], 201);
    }

    #[OA\Post(
        path: '/event-occurrences/{occurrence}/participants/bulk',
        summary: 'Add multiple occurrence participants',
        description: 'Adds multiple users to an occurrence after duplicate, capacity, and active-service eligibility checks. When the event requires a limited service, one access is consumed per user. The operation is atomic.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BulkAddEventParticipantsRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Participants added.', content: new OA\JsonContent(ref: '#/components/schemas/BulkAddEventParticipantsResponse')),
            new OA\Response(response: 400, description: 'Not enough available places for the selected active participant status.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden or one of the selected users is not eligible for the active-service requirement.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error or one of the selected users is already registered for this occurrence.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function bulkStore(BulkAddEventParticipantsRequest $request, EventOccurrence $occurrence): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'];
        $status = $data['status'] ?? 'registered';
        $occurrence->load('event.requiredService');

        if ($occurrence->participants()->whereKey($userIds)->exists()) {
            return $this->error('One or more users are already registered for this occurrence.', 422);
        }

        $users = User::query()->whereKey($userIds)->get();

        if ($users->count() !== count($userIds)) {
            return $this->error('One or more users were not found.', 404);
        }

        foreach ($users as $user) {
            if (! $this->eligibility->canUserJoinOccurrence($user, $occurrence)) {
                return $this->error('One or more users do not have the required active service.', 403);
            }
        }

        $activeStatuses = ['registered', 'attended'];
        $maxParticipants = $occurrence->event->max_participants;
        if ($maxParticipants !== null && in_array($status, $activeStatuses, true)) {
            $availablePlaces = $maxParticipants - $occurrence->activeParticipants()->count();
            if (count($userIds) > $availablePlaces) {
                return $this->error('Event occurrence does not have enough available places.', 400);
            }
        }

        DB::transaction(function () use ($occurrence, $userIds, $users, $data, $status): void {
            $attributes = [];
            foreach ($userIds as $userId) {
                $user = $users->firstWhere('id', $userId);
                if ($user !== null) {
                    $this->serviceLifecycle->consumeEventAccess($user, $occurrence->event);
                }
                $attributes[$userId] = [
                    'status' => $status,
                    'registered_at' => $data['registered_at'] ?? now(),
                    'notes' => $data['notes'] ?? null,
                ];
            }

            $occurrence->participants()->attach($attributes);
        });

        $participants = $occurrence->participants()->whereKey($userIds)->orderBy('last_name')->orderBy('first_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Participants added successfully.',
            'data' => EventParticipantResource::collection($participants),
        ], 201);
    }

    #[OA\Patch(
        path: '/event-occurrences/{occurrence}/participants/{user}',
        summary: 'Update occurrence participant',
        description: 'Updates the participant pivot data for a concrete event occurrence.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEventParticipantRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Participant updated.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventParticipant')])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function update(UpdateEventParticipantRequest $request, EventOccurrence $occurrence, User $user): JsonResponse
    {
        $participant = $occurrence->participants()->whereKey($user->id)->first();

        if (! $participant) {
            return $this->error('Participant not found for this occurrence.', 404);
        }

        $data = $request->validated();
        $currentStatus = $participant->pivot->status;
        $nextStatus = $data['status'] ?? $currentStatus;
        $activeStatuses = ['registered', 'attended'];
        $isActivatingParticipant = ! in_array($currentStatus, $activeStatuses, true)
            && in_array($nextStatus, $activeStatuses, true);

        if ($isActivatingParticipant) {
            $occurrence->load('event.requiredService');

            if (! $this->eligibility->canUserJoinOccurrence($user, $occurrence)) {
                return $this->error('User does not have the required active service.', 403);
            }

            $maxParticipants = $occurrence->event->max_participants;
            if ($maxParticipants !== null && $occurrence->activeParticipants()->count() >= $maxParticipants) {
                return $this->error('Event occurrence has reached the maximum number of participants.', 400);
            }
        }

        $attributes = [];
        foreach (['status', 'registered_at', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        $occurrence->participants()->updateExistingPivot($user->id, $attributes);

        $participant = $occurrence->participants()->whereKey($user->id)->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Participant updated successfully.',
            'data' => new EventParticipantResource($participant),
        ]);
    }

    #[OA\Delete(
        path: '/event-occurrences/{occurrence}/participants/{user}',
        summary: 'Remove occurrence participant',
        description: 'Removes a user from the participant list for a concrete event occurrence.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Participant removed.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function destroy(EventOccurrence $occurrence, User $user): JsonResponse
    {
        if (! $occurrence->participants()->whereKey($user->id)->exists()) {
            return $this->error('Participant not found for this occurrence.', 404);
        }

        $occurrence->participants()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Participant removed successfully.',
            'data' => null,
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => [],
        ], $status);
    }
}
