<?php

namespace App\Events\Http\Controllers\Api;

use App\Events\Http\Requests\AddEventParticipantRequest;
use App\Events\Http\Requests\BulkAddEventParticipantsRequest;
use App\Events\Http\Requests\UpdateEventParticipantRequest;
use App\Events\Http\Resources\EventEligibleParticipantResource;
use App\Events\Http\Resources\EventParticipantResource;
use App\Events\Models\EventOccurrence;
use App\Events\Models\EventParticipant;
use App\Events\Services\EventEligibilityService;
use App\Events\Services\EventParticipantInvoiceService;
use App\Events\Services\EventParticipantService;
use App\Events\Services\OccurrenceAttendancePdfService;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class EventParticipantController extends Controller
{
    public function __construct(
        private readonly EventParticipantService $participants,
        private readonly ServiceLifecycleService $serviceLifecycle,
        private readonly EventEligibilityService $eligibility,
        private readonly EventParticipantInvoiceService $invoices,
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
        $participants = $occurrence->participants()->orderBy('last_name')->orderBy('first_name')->paginate(request()->integer('per_page', 15));
        $this->loadPayments($participants->getCollection());

        return EventParticipantResource::collection($participants);
    }

    public function downloadPdf(Request $request, EventOccurrence $occurrence, OccurrenceAttendancePdfService $pdf): Response
    {
        abort_unless((int) $occurrence->organization_id === (int) $request->user()->organization_id, 404);

        return $pdf->download($occurrence);
    }

    #[OA\Get(
        path: '/event-occurrences/{occurrence}/eligible-participants',
        summary: 'List users eligible for quick add',
        description: 'Returns users that can be added to a concrete event occurrence: visible in the current organization, not already participants, and matching the active-service requirement when the event requires one. With `include_ineligible=1`, users that do not satisfy the service requirement are returned too. Every item carries `eligible` and `missing_service_groups` (indices of the unmet event `service_groups`; empty when eligible or when the event only requires any active service). Ineligible users can be added only with `force: true`, which needs `checkins.override`.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'include_ineligible', required: false, description: 'When true, also returns users missing the required services (marked `eligible: false`).', schema: new OA\Schema(type: 'boolean')),
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
        $occurrence->load('event.serviceRequirements.service');
        $event = $occurrence->event;
        $serviceGroups = $event?->serviceGroupIds() ?? [];
        $includeIneligible = $request->boolean('include_ineligible');

        $users = User::query()
            ->with('activeServices')
            ->whereDoesntHave('eventOccurrences', fn ($query) => $query->whereKey($occurrence->id))
            ->when(! $includeIneligible && $event?->requires_active_service, function ($query) use ($serviceGroups): void {
                if ($serviceGroups === []) {
                    $query->whereHas('activeServices');

                    return;
                }

                // AND between groups, OR inside a group.
                foreach ($serviceGroups as $serviceIds) {
                    $query->whereHas('activeServices', fn ($query) => $query->whereIn('services.id', $serviceIds));
                }
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
            ->paginate($request->integer('per_page', 15))
            ->through(function (User $user) use ($event): EventEligibleParticipantResource {
                $verdict = $event === null
                    ? ['eligible' => true, 'missing_service_groups' => []]
                    : $this->eligibility->evaluateWithLoadedServices($user, $event);

                return new EventEligibleParticipantResource($user, $verdict['eligible'], $verdict['missing_service_groups']);
            });

        return EventEligibleParticipantResource::collection($users);
    }

    #[OA\Post(
        path: '/event-occurrences/{occurrence}/participants',
        summary: 'Add occurrence participant',
        description: 'Adds a user to an occurrence after duplicate, capacity, and active-service eligibility checks. The user must satisfy every required service group of the event (any one service per group). One access is consumed atomically per group (unlimited services in a group consume nothing). A 403 missing_required_service response lists the unmet group indices in `missing_service_groups`. With `force: true` (requires `checkins.override`, otherwise 403 reason=override_not_allowed) a user missing required services is added anyway, consumes no access and is marked `access_override: true`; the response then has `forced: true`. Duplicate and capacity checks still block a forced add. The response always includes a `requires_payment` flag mirroring the parent event; it is informational only and never blocks the addition.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AddEventParticipantRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Success.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Participant added.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventParticipant'), new OA\Property(property: 'requires_payment', type: 'boolean', example: false), new OA\Property(property: 'forced', type: 'boolean', example: false, description: 'True when the user lacked required services and was added through `force`.')])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden, missing_required_service (with missing_service_groups), or override_not_allowed when `force` is sent without `checkins.override`.', content: new OA\JsonContent(ref: '#/components/schemas/MissingRequiredServiceResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(AddEventParticipantRequest $request, EventOccurrence $occurrence): JsonResponse
    {
        $data = $request->validated();
        $force = (bool) ($data['force'] ?? false);

        if ($force && ! $request->user()->hasRight('checkins.override')) {
            return $this->overrideNotAllowed();
        }

        $user = User::query()->findOrFail($data['user_id']);
        $occurrence->load('event.serviceRequirements.service');

        $result = $this->participants->evaluate($user, $occurrence, $force);

        if (! $result->ok) {
            return match ($result->reason) {
                'already_registered' => $this->error('User is already registered for this occurrence.', 422),
                'missing_required_service' => $this->error('User does not have the required active service.', 403, [
                    'reason' => 'missing_required_service',
                    'missing_service_groups' => $result->missingServiceGroups,
                ]),
                default => $this->error('Event occurrence has reached the maximum number of participants.', 400),
            };
        }

        DB::transaction(function () use ($occurrence, $user, $data, $result): void {
            // A forced add is an exception, like a check-in override: no access is consumed.
            if (! $result->forced) {
                $this->serviceLifecycle->consumeEventAccess($user, $occurrence->event);
            }
            $occurrence->participants()->attach($user->id, [
                'status' => $data['status'] ?? 'registered',
                'registered_at' => $data['registered_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'access_override' => $result->forced,
            ]);
        });

        $participant = $occurrence->participants()->whereKey($user->id)->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Participant added successfully.',
            'data' => new EventParticipantResource($participant),
            'requires_payment' => $result->requiresPayment,
            'forced' => $result->forced,
        ], 201);
    }

    #[OA\Post(
        path: '/event-occurrences/{occurrence}/participants/bulk',
        summary: 'Add multiple occurrence participants',
        description: 'Adds multiple users to an occurrence after duplicate, capacity, and active-service eligibility checks. Each user must satisfy every required service group; one access is consumed per group per user. The operation on the target occurrence is atomic. With `force: true` (requires `checkins.override`, otherwise 403 reason=override_not_allowed) users missing required services are added anyway on the target occurrence only, consume no access, are marked `access_override: true` and are listed in `forced_user_ids`; eligible users in the same request consume normally. Duplicate and capacity checks still block. The response always includes a `requires_payment` flag mirroring the parent event; it is informational only and never blocks the addition. When `apply_to_future_occurrences` is true, the same users are also mirrored (best effort, never forced) onto the sibling scheduled future occurrences (today or later) of the same event; per-occurrence/per-user failures there (already registered, full capacity, missing required service, no service access to consume) are reported in `future_occurrences_updated` instead of failing the whole request.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BulkAddEventParticipantsRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Participants added.', content: new OA\JsonContent(ref: '#/components/schemas/BulkAddEventParticipantsResponse')),
            new OA\Response(response: 400, description: 'Not enough available places for the selected active participant status.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden, one of the selected users is not eligible for the active-service requirement (without force), or override_not_allowed when `force` is sent without `checkins.override`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error or one of the selected users is already registered for this occurrence.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function bulkStore(BulkAddEventParticipantsRequest $request, EventOccurrence $occurrence): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'];
        $status = $data['status'] ?? 'registered';
        $force = (bool) ($data['force'] ?? false);

        if ($force && ! $request->user()->hasRight('checkins.override')) {
            return $this->overrideNotAllowed();
        }

        $occurrence->load('event.serviceRequirements.service');

        if ($this->participants->isAlreadyRegistered($occurrence, $userIds)) {
            return $this->error('One or more users are already registered for this occurrence.', 422);
        }

        $users = User::query()->whereKey($userIds)->get();

        if ($users->count() !== count($userIds)) {
            return $this->error('One or more users were not found.', 404);
        }

        $forcedUserIds = [];
        foreach ($users as $user) {
            if (! $this->participants->isEligible($user, $occurrence)) {
                if (! $force) {
                    return $this->error('One or more users do not have the required active service.', 403);
                }

                $forcedUserIds[] = (int) $user->id;
            }
        }

        $activeStatuses = ['registered', 'attended'];
        if (in_array($status, $activeStatuses, true) && ! $this->participants->hasAvailableCapacity($occurrence, count($userIds))) {
            return $this->error('Event occurrence does not have enough available places.', 400);
        }

        $requiresPayment = $this->participants->requiresPayment($occurrence);

        DB::transaction(function () use ($occurrence, $userIds, $users, $data, $status, $forcedUserIds): void {
            $attributes = [];
            foreach ($userIds as $userId) {
                $isForced = in_array((int) $userId, $forcedUserIds, true);
                $user = $users->firstWhere('id', $userId);
                // Forced users are an exception, like a check-in override: no access is consumed.
                if ($user !== null && ! $isForced) {
                    $this->serviceLifecycle->consumeEventAccess($user, $occurrence->event);
                }
                $attributes[$userId] = [
                    'status' => $status,
                    'registered_at' => $data['registered_at'] ?? now(),
                    'notes' => $data['notes'] ?? null,
                    'access_override' => $isForced,
                ];
            }

            $occurrence->participants()->attach($attributes);
        });

        $participants = $occurrence->participants()->whereKey($userIds)->orderBy('last_name')->orderBy('first_name')->get();

        $response = [
            'success' => true,
            'message' => 'Participants added successfully.',
            'data' => EventParticipantResource::collection($participants),
            'requires_payment' => $requiresPayment,
            'forced_user_ids' => $forcedUserIds,
        ];

        if ($data['apply_to_future_occurrences'] ?? false) {
            $response['future_occurrences_updated'] = $this->applyToFutureOccurrences($occurrence, $users, $status, $data);
        }

        return response()->json($response, 201);
    }

    /**
     * Best-effort mirror of a bulk add onto the sibling future occurrences of
     * the same event (same recurring series). Unlike the primary target
     * occurrence handled above, a failure for one user or one occurrence
     * (already registered, no capacity, missing required service, or no
     * available service access to consume) never aborts the whole operation;
     * it is only reported back per occurrence so the caller can show a
     * summary such as "added to N future occurrences, M skipped".
     *
     * "Future" means scheduled occurrences of the same event with
     * `occurrence_date` today or later (today inclusive, matching
     * `EventOccurrenceGeneratorService::regenerateFutureOpenOccurrences`),
     * excluding the occurrence explicitly targeted by this request and
     * excluding cancelled/completed occurrences.
     *
     * @param \Illuminate\Support\Collection<int, User> $users
     * @return array<int, array{occurrence_id: int, occurrence_date: string, added_user_ids: array<int, int>, skipped: array<int, array{user_id: int, reason: string}>}>
     */
    private function applyToFutureOccurrences(EventOccurrence $occurrence, \Illuminate\Support\Collection $users, string $status, array $data): array
    {
        $activeStatuses = ['registered', 'attended'];

        $futureOccurrences = $occurrence->event->occurrences()
            ->where('id', '!=', $occurrence->id)
            ->where('status', 'scheduled')
            ->whereDate('occurrence_date', '>=', now()->toDateString())
            ->orderBy('occurrence_date')
            ->get();

        $summary = [];

        foreach ($futureOccurrences as $futureOccurrence) {
            $addedUserIds = [];
            $skipped = [];

            foreach ($users as $user) {
                if ($this->participants->isAlreadyRegistered($futureOccurrence, $user->id)) {
                    $skipped[] = ['user_id' => $user->id, 'reason' => 'already_registered'];
                    continue;
                }

                if (! $this->participants->isEligible($user, $futureOccurrence)) {
                    $skipped[] = [
                        'user_id' => $user->id,
                        'reason' => 'missing_required_service',
                        'missing_service_groups' => $this->participants->missingServiceGroups($user, $futureOccurrence),
                    ];
                    continue;
                }

                if (in_array($status, $activeStatuses, true) && ! $this->participants->hasAvailableCapacity($futureOccurrence)) {
                    $skipped[] = ['user_id' => $user->id, 'reason' => 'capacity_full'];
                    continue;
                }

                try {
                    DB::transaction(function () use ($futureOccurrence, $user, $status, $data): void {
                        $this->serviceLifecycle->consumeEventAccess($user, $futureOccurrence->event);
                        $futureOccurrence->participants()->attach($user->id, [
                            'status' => $status,
                            'registered_at' => $data['registered_at'] ?? now(),
                            'notes' => $data['notes'] ?? null,
                        ]);
                    });
                } catch (\Illuminate\Validation\ValidationException) {
                    $skipped[] = ['user_id' => $user->id, 'reason' => 'service_access_unavailable'];
                    continue;
                }

                $addedUserIds[] = $user->id;
            }

            $summary[] = [
                'occurrence_id' => $futureOccurrence->id,
                'occurrence_date' => $futureOccurrence->occurrence_date->toDateString(),
                'added_user_ids' => $addedUserIds,
                'skipped' => $skipped,
            ];
        }

        return $summary;
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
            new OA\Response(response: 403, description: 'Forbidden, or missing_required_service (with missing_service_groups).', content: new OA\JsonContent(ref: '#/components/schemas/MissingRequiredServiceResponse')),
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
            $occurrence->load('event.serviceRequirements.service');

            if (! $this->participants->isEligible($user, $occurrence)) {
                return $this->error('User does not have the required active service.', 403, [
                    'reason' => 'missing_required_service',
                    'missing_service_groups' => $this->participants->missingServiceGroups($user, $occurrence),
                ]);
            }

            if (! $this->participants->hasAvailableCapacity($occurrence)) {
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

    #[OA\Post(
        path: '/event-occurrences/{occurrence}/participants/{user}/invoice',
        summary: 'Generate participant invoice',
        description: 'Issues (idempotently) an invoice number from the organization invoice sequence for a participation in a paid event (requires_payment + payment_amount > 0). Repeated calls return the same number. Amount = event payment_amount, currency = event payment_type.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice number assigned (or already present).', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventParticipant')])),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Occurrence (other organization) or participant not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'reason=event_not_payable (event without requires_payment/payment_amount) or reason=participant_cancelled.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function generateInvoice(Request $request, EventOccurrence $occurrence, User $user): JsonResponse
    {
        $participant = $this->participantOrFail($request, $occurrence, $user);
        $occurrence->loadMissing('event');

        if (! EventParticipantInvoiceService::isPayable($occurrence->event)) {
            return $this->error('Event does not require payment; no invoice can be issued.', 422, ['reason' => 'event_not_payable']);
        }
        if (blank($participant->invoice_number) && $participant->status === 'cancelled') {
            return $this->error('Cancelled participations cannot be invoiced.', 422, ['reason' => 'participant_cancelled']);
        }

        $this->invoices->generate($participant);

        $member = $occurrence->participants()->whereKey($user->id)->firstOrFail();
        $this->loadPayments(new EloquentCollection([$member]));

        return response()->json([
            'success' => true,
            'message' => 'Invoice generated successfully.',
            'data' => new EventParticipantResource($member),
        ]);
    }

    #[OA\Get(
        path: '/event-occurrences/{occurrence}/participants/{user}/invoice/{format}',
        summary: 'Download participant invoice',
        description: 'Downloads the participant invoice as PDF (default) or UBL e-Factura XML. 404 when no invoice number was generated.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'format', required: true, description: 'Optional in practice; omitted = pdf.', schema: new OA\Schema(type: 'string', enum: ['pdf', 'xml'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice document.', content: [new OA\MediaType(mediaType: 'application/pdf'), new OA\MediaType(mediaType: 'application/xml')]),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found or no invoice generated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function invoice(Request $request, EventOccurrence $occurrence, User $user, string $format = 'pdf'): Response
    {
        $participant = $this->participantOrFail($request, $occurrence, $user);
        abort_if(blank($participant->invoice_number), 404);

        return $format === 'xml'
            ? $this->invoices->xmlDownload($participant)
            : $this->invoices->download($participant);
    }

    private function participantOrFail(Request $request, EventOccurrence $occurrence, User $user): EventParticipant
    {
        abort_unless((int) $occurrence->organization_id === (int) $request->user()->organization_id, 404);

        return EventParticipant::query()
            ->where('event_occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /**
     * Eager-load payments on the pivots of a participant (User) collection.
     */
    private function loadPayments(\Illuminate\Support\Collection $members): void
    {
        $pivots = $members->pluck('pivot')->filter();
        if ($pivots->isNotEmpty()) {
            (new EloquentCollection($pivots->all()))->load('payments');
        }
    }

    private function overrideNotAllowed(): JsonResponse
    {
        return $this->error('Forcing participants requires the checkins.override right.', 403, [
            'reason' => 'override_not_allowed',
        ]);
    }

    /**
     * @param array<string, mixed> $extra additional top-level keys (e.g. `reason`, `missing_service_groups`)
     */
    private function error(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => [],
            ...$extra,
        ], $status);
    }
}
