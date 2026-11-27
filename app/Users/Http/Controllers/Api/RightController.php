<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Resources\RightResource;
use App\Users\Models\Right;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only access to the platform-wide rights catalogue.
 *
 * Rights have no organization_id and authorization matches them by name, so any write
 * (create/rename/delete) would change authorization for every organization. The catalogue
 * is therefore maintained only in code (database/seeders/ApplicationRights.php).
 */
class RightController extends Controller
{
    public function __construct(private readonly OrganizationAccessService $organizationAccess) {}

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

    public function show(Right $right): RightResource
    {
        abort_if(
            $this->organizationAccess->isRightDisabledForOrganization($right->name, request()->user()?->organization_id),
            404
        );

        return new RightResource($right->loadCount('groups'));
    }
}
