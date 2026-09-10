<?php

namespace App\Events\Http\Controllers\Api;

use App\Events\Http\Resources\EventOccurrenceResource;
use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class EventOccurrenceController extends Controller
{
    #[OA\Get(
        path: '/event-occurrences',
        summary: 'List all event occurrences',
        description: 'Returns paginated occurrences across all events for calendar views with date/status filters.',
        security: [['bearerAuth' => []]],
        tags: ['Event Occurrences'],
        parameters: [
            new OA\QueryParameter(name: 'date_from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'date_to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string', enum: ['scheduled', 'cancelled', 'completed'])),
            new OA\QueryParameter(name: 'category_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Occurrences list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventOccurrence'))])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function all(Request $request): AnonymousResourceCollection
    {
        $occurrences = EventOccurrence::query()
            ->with(['event.category', 'event.requiredService'])
            ->withCount('participants')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('event', fn ($eventQuery) => $eventQuery->where('category_id', $request->integer('category_id'))))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('occurrence_date', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('occurrence_date', '<=', $request->date('date_to')->toDateString()))
            ->orderBy('start_datetime')
            ->paginate($request->integer('per_page', 200));

        return EventOccurrenceResource::collection($occurrences);
    }

    #[OA\Get(
        path: '/events/{event}/occurrences',
        summary: 'List event occurrences',
        description: 'Returns paginated occurrences for an event with date/status filters and participant counts.',
        security: [['bearerAuth' => []]],
        tags: ['Event Occurrences'],
        parameters: [
            new OA\PathParameter(name: 'event', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'date_from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'date_to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string', enum: ['scheduled', 'cancelled', 'completed'])),
            new OA\QueryParameter(name: 'event_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'sort', required: false, schema: new OA\Schema(type: 'string', example: 'start_datetime')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Occurrences list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventOccurrence'))])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function index(Request $request, Event $event): AnonymousResourceCollection
    {
        $occurrences = $event->occurrences()
            ->with(['event.category', 'event.requiredService'])
            ->withCount('participants')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->integer('event_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('occurrence_date', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('occurrence_date', '<=', $request->date('date_to')->toDateString()))
            ->orderBy('start_datetime')
            ->paginate($request->integer('per_page', 15));

        return EventOccurrenceResource::collection($occurrences);
    }

    #[OA\Get(
        path: '/event-occurrences/{occurrence}',
        summary: 'Show event occurrence',
        description: 'Returns details for a concrete event occurrence including participant counts and available places.',
        security: [['bearerAuth' => []]],
        tags: ['Event Occurrences'],
        parameters: [new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Occurrence details.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventOccurrence')])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function show(EventOccurrence $occurrence): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Event occurrence retrieved successfully.',
            'data' => new EventOccurrenceResource($occurrence->load(['event.category', 'event.requiredService'])->loadCount('participants')),
        ]);
    }
}
