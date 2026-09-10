<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreLocationGroupRequest;
use App\Users\Http\Requests\UpdateLocationGroupRequest;
use App\Users\Http\Resources\LocationGroupResource;
use App\Users\Models\LocationGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationGroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $locationGroups = LocationGroup::query()
            ->withCount('locations')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($request->integer('per_page', 15));

        return LocationGroupResource::collection($locationGroups);
    }

    public function store(StoreLocationGroupRequest $request): JsonResponse
    {
        $locationGroup = LocationGroup::query()->create($request->validated());

        return (new LocationGroupResource($locationGroup->loadCount('locations')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LocationGroup $locationGroup): LocationGroupResource
    {
        return new LocationGroupResource($locationGroup->load([
            'locations' => fn ($query) => $query->orderBy('name', 'asc'),
        ])->loadCount('locations'));
    }

    public function update(UpdateLocationGroupRequest $request, LocationGroup $locationGroup): LocationGroupResource
    {
        $locationGroup->update($request->validated());

        return new LocationGroupResource($locationGroup->load([
            'locations' => fn ($query) => $query->orderBy('name', 'asc'),
        ])->loadCount('locations'));
    }

    public function destroy(LocationGroup $locationGroup): JsonResponse
    {
        $locationGroup->delete();

        return response()->json(status: 204);
    }
}
