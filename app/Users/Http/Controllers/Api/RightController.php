<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreRightRequest;
use App\Users\Http\Requests\UpdateRightRequest;
use App\Users\Http\Resources\RightResource;
use App\Users\Models\Right;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RightController extends Controller
{
    public function __construct(private readonly OrganizationAccessService $organizationAccess)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Right::query()->withCount('groups');
        $this->organizationAccess->applyAvailableRightsFilter($query, $request->user()?->organization_id);

        $rights = $query
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return RightResource::collection($rights);
    }

    public function store(StoreRightRequest $request): JsonResponse
    {
        $right = Right::query()->create($request->validated());

        return (new RightResource($right->loadCount('groups')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Right $right): RightResource
    {
        abort_if(
            $this->organizationAccess->isRightDisabledForOrganization($right->name, request()->user()?->organization_id),
            404
        );

        return new RightResource($right->loadCount('groups'));
    }

    public function update(UpdateRightRequest $request, Right $right): RightResource
    {
        $right->update($request->validated());

        return new RightResource($right->loadCount('groups'));
    }

    public function destroy(Right $right): JsonResponse
    {
        if ($error = $this->organizationAccess->deleteBlockedByManyToManyResponse(
            $right,
            ['groups'],
            request()->user()?->organization_id,
        )) {
            return response()->json($error, 422);
        }

        if ($right->groups()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a right assigned to groups.',
            ], 422);
        }

        DB::transaction(fn () => $right->delete());

        return response()->json(status: 204);
    }
}
