<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreGroupRequest;
use App\Users\Http\Requests\UpdateGroupRequest;
use App\Users\Http\Resources\GroupResource;
use App\Users\Models\Group;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function __construct(private readonly OrganizationAccessService $organizationAccess)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $organizationId = $request->user()?->organization_id;

        $groups = Group::query()
            ->with([
                'rights' => fn ($query) => $this->organizationAccess->applyAvailableRightsFilter($query, $organizationId),
            ])
            ->withCount('users')
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

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rightIds = $data['right_ids'] ?? [];
        unset($data['right_ids']);

        $group = DB::transaction(function () use ($data, $rightIds): Group {
            $group = Group::query()->create($data);
            $group->rights()->sync($rightIds);

            return $group;
        });

        return (new GroupResource($this->loadGroupForResponse($group, $request->user()?->organization_id)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Group $group): GroupResource
    {
        return new GroupResource($this->loadGroupForResponse($group, request()->user()?->organization_id));
    }

    public function update(UpdateGroupRequest $request, Group $group): GroupResource
    {
        $data = $request->validated();
        $rightIds = $data['right_ids'] ?? null;
        unset($data['right_ids']);

        DB::transaction(function () use ($group, $data, $rightIds): void {
            $group->update($data);

            if ($rightIds !== null) {
                $group->rights()->sync($rightIds);
            }
        });

        return new GroupResource($this->loadGroupForResponse($group, $request->user()?->organization_id));
    }

    public function destroy(Group $group): JsonResponse
    {
        if ($error = $this->organizationAccess->deleteBlockedByManyToManyResponse($group, ['users', 'rights', 'articles'])) {
            return response()->json($error, 422);
        }

        if ($group->users()->withoutGlobalScopes()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a group that still has users.',
            ], 422);
        }

        DB::transaction(function () use ($group): void {
            $group->rights()->detach();
            $group->delete();
        });

        return response()->json(status: 204);
    }

    private function loadGroupForResponse(Group $group, ?int $organizationId): Group
    {
        return $group->load([
            'rights' => fn ($query) => $this->organizationAccess->applyAvailableRightsFilter($query, $organizationId),
        ])->loadCount('users');
    }
}
