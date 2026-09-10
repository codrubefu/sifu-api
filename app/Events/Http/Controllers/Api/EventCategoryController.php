<?php

namespace App\Events\Http\Controllers\Api;

use App\Events\Http\Requests\StoreEventCategoryRequest;
use App\Events\Http\Requests\UpdateEventCategoryRequest;
use App\Events\Http\Resources\EventCategoryResource;
use App\Events\Models\EventCategory;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class EventCategoryController extends Controller
{
    #[OA\Get(
        path: '/event-categories',
        summary: 'List event categories',
        security: [['bearerAuth' => []]],
        tags: ['Event Categories'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'is_active', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Event categories list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventCategory'))]))],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = EventCategory::query()
            ->withCount('events')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return EventCategoryResource::collection($categories);
    }

    #[OA\Post(
        path: '/event-categories',
        summary: 'Create event category',
        security: [['bearerAuth' => []]],
        tags: ['Event Categories'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreEventCategoryRequest')),
        responses: [new OA\Response(response: 201, description: 'Event category created.', content: new OA\JsonContent(ref: '#/components/schemas/EventCategory'))],
    )]
    public function store(StoreEventCategoryRequest $request): JsonResponse
    {
        return (new EventCategoryResource(EventCategory::query()->create($request->validated())->loadCount('events')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/event-categories/{eventCategory}',
        summary: 'Show event category',
        security: [['bearerAuth' => []]],
        tags: ['Event Categories'],
        parameters: [new OA\PathParameter(name: 'eventCategory', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Event category details.', content: new OA\JsonContent(ref: '#/components/schemas/EventCategory'))],
    )]
    public function show(EventCategory $eventCategory): EventCategoryResource
    {
        return new EventCategoryResource($eventCategory->loadCount('events'));
    }

    #[OA\Patch(
        path: '/event-categories/{eventCategory}',
        summary: 'Update event category',
        security: [['bearerAuth' => []]],
        tags: ['Event Categories'],
        parameters: [new OA\PathParameter(name: 'eventCategory', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEventCategoryRequest')),
        responses: [new OA\Response(response: 200, description: 'Event category updated.', content: new OA\JsonContent(ref: '#/components/schemas/EventCategory'))],
    )]
    public function update(UpdateEventCategoryRequest $request, EventCategory $eventCategory): EventCategoryResource
    {
        $eventCategory->update($request->validated());

        return new EventCategoryResource($eventCategory->loadCount('events'));
    }

    #[OA\Delete(
        path: '/event-categories/{eventCategory}',
        summary: 'Delete event category',
        security: [['bearerAuth' => []]],
        tags: ['Event Categories'],
        parameters: [new OA\PathParameter(name: 'eventCategory', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Event category deleted.')],
    )]
    public function destroy(EventCategory $eventCategory): JsonResponse
    {
        DB::transaction(function () use ($eventCategory): void {
            $eventCategory->events()->update(['category_id' => null]);
            $eventCategory->delete();
        });

        return response()->json(status: 204);
    }
}
