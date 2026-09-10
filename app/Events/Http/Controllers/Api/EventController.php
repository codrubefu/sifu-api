<?php

namespace App\Events\Http\Controllers\Api;

use App\Notifications\Events\NotificationRequested;
use App\Events\Http\Requests\StoreEventRequest;
use App\Events\Http\Requests\UpdateEventRequest;
use App\Events\Http\Resources\EventResource;
use App\Events\Models\Event;
use App\Events\Services\EventOccurrenceGeneratorService;
use App\Users\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    public function __construct(private readonly EventOccurrenceGeneratorService $occurrences)
    {
    }

    #[OA\Get(
        path: '/events',
        summary: 'List events',
        description: 'Returns paginated events with filters for category, status, recurrence, service requirement, search, and sorting.',
        security: [['bearerAuth' => []]],
        tags: ['Events'],
        parameters: [
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'cancelled'])),
            new OA\QueryParameter(name: 'category_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'recurrence_type', required: false, schema: new OA\Schema(type: 'string', enum: ['once', 'weekly', 'monthly'])),
            new OA\QueryParameter(name: 'requires_active_service', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\QueryParameter(name: 'requires_payment', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'sort', required: false, schema: new OA\Schema(type: 'string', enum: ['created_at', 'start_date', 'title'])),
            new OA\QueryParameter(name: 'direction', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Events list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Event'))])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = in_array($request->string('sort')->toString(), ['created_at', 'start_date', 'title'], true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $events = Event::query()
            ->with(['category', 'requiredService'])
            ->withCount('occurrences')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('recurrence_type'), fn ($query) => $query->where('recurrence_type', $request->string('recurrence_type')->toString()))
            ->when($request->filled('requires_active_service'), fn ($query) => $query->where('requires_active_service', $request->boolean('requires_active_service')))
            ->when($request->filled('requires_payment'), fn ($query) => $query->where('requires_payment', $request->boolean('requires_payment')))
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('title', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 15));

        return EventResource::collection($events);
    }

    #[OA\Post(
        path: '/events',
        summary: 'Create event',
        description: 'Creates an event and generates its initial occurrences based on recurrence settings.',
        security: [['bearerAuth' => []]],
        tags: ['Events'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreEventRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Success.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Event created.', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'data', ref: '#/components/schemas/Event')])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = DB::transaction(function () use ($request): Event {
            $event = Event::query()->create($request->validated());
            $this->occurrences->generateForNewEvent($event);

            return $event;
        });

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully.',
            'data' => new EventResource($event->load(['category', 'requiredService', 'occurrences'])->loadCount('occurrences')),
        ], 201);
    }

    #[OA\Get(
        path: '/events/{event}',
        summary: 'Show event',
        description: 'Returns details for a single event with generated occurrences.',
        security: [['bearerAuth' => []]],
        tags: ['Events'],
        parameters: [new OA\PathParameter(name: 'event', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Event details.', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'data', ref: '#/components/schemas/Event')])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Event retrieved successfully.',
            'data' => new EventResource($event->load(['category', 'requiredService', 'occurrences'])->loadCount('occurrences')),
        ]);
    }

    #[OA\Put(
        path: '/events/{event}',
        summary: 'Replace event',
        description: 'Updates an event and regenerates future occurrences without participants.',
        security: [['bearerAuth' => []]],
        tags: ['Events'],
        parameters: [new OA\PathParameter(name: 'event', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEventRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Event updated.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    #[OA\Patch(
        path: '/events/{event}',
        summary: 'Update event',
        description: 'Partially updates an event and regenerates future occurrences without participants.',
        security: [['bearerAuth' => []]],
        tags: ['Events'],
        parameters: [new OA\PathParameter(name: 'event', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEventRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Event updated.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        DB::transaction(function () use ($request, $event): void {
            $oldStatus = $event->status;
            $event->update($request->validated());
            $scheduleChanged = array_intersect(
                ['start_time', 'end_time', 'start_date', 'end_date', 'recurrence_type', 'recurrence_days', 'monthly_day', 'location'],
                array_keys($event->getChanges()),
            ) !== [];
            $this->occurrences->regenerateFutureOpenOccurrences($event->refresh());

            $type = $oldStatus !== 'active' && $event->status === 'active'
                ? NotificationRequested::RESUMED
                : ($scheduleChanged ? NotificationRequested::SCHEDULE_CHANGED : null);

            if ($type) {
                $eventId = $event->id;
                $version = $event->updated_at->getTimestamp();
                DB::afterCommit(function () use ($eventId, $version, $type): void {
                    $event = Event::withoutGlobalScopes()->find($eventId);
                    User::withoutGlobalScopes()->whereHas('eventOccurrences', fn ($query) => $query->where('event_occurrences.event_id', $eventId))
                        ->each(fn (User $user) => NotificationRequested::dispatch(
                            $user, $type, "{$type}:{$eventId}:{$version}", ['event' => $event?->title ?? (string) $eventId]
                        ));
                });
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully.',
            'data' => new EventResource($event->load(['category', 'requiredService', 'occurrences'])->loadCount('occurrences')),
        ]);
    }

    #[OA\Delete(
        path: '/events/{event}',
        summary: 'Delete event',
        description: 'Soft deletes an event, deleting future occurrences without participants and cancelling future occurrences with participants.',
        security: [['bearerAuth' => []]],
        tags: ['Events'],
        parameters: [new OA\PathParameter(name: 'event', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Event deleted.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function destroy(Event $event): JsonResponse
    {
        DB::transaction(function () use ($event): void {
            $event->occurrences()
                ->whereDate('occurrence_date', '>=', now()->toDateString())
                ->doesntHave('participants')
                ->delete();

            $event->occurrences()
                ->whereDate('occurrence_date', '>=', now()->toDateString())
                ->whereHas('participants')
                ->update(['status' => 'cancelled']);

            $event->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully.',
            'data' => null,
        ]);
    }
}
