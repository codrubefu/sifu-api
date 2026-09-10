<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreGradeRequest;
use App\Users\Http\Requests\StoreUserGradeRequest;
use App\Users\Http\Requests\UpdateGradeRequest;
use App\Users\Http\Requests\UpdateUserGradeRequest;
use App\Users\Http\Resources\GradeResource;
use App\Users\Http\Resources\UserGradeResource;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\Grade;
use App\Users\Models\User;
use App\Users\Models\UserGrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $grades = Grade::query()
            ->withCount('users')
            ->when($request->boolean('with_trashed'), fn (Builder $query) => $query->withTrashed())
            ->when($request->boolean('only_trashed'), fn (Builder $query) => $query->onlyTrashed())
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
            })
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return GradeResource::collection($grades);
    }

    public function store(StoreGradeRequest $request): JsonResponse
    {
        $grade = Grade::query()->create($request->validated());

        return (new GradeResource($grade->loadCount('users')))->response()->setStatusCode(201);
    }

    public function show(Grade $grade): GradeResource
    {
        return new GradeResource($grade->loadCount('users'));
    }

    public function update(UpdateGradeRequest $request, Grade $grade): GradeResource
    {
        $grade->update($request->validated());

        return new GradeResource($grade->loadCount('users'));
    }

    public function destroy(Grade $grade): JsonResponse
    {
        $grade->delete();

        return response()->json(status: 204);
    }

    public function users(Request $request, Grade $grade): AnonymousResourceCollection
    {
        $activeGradeIds = UserGrade::query()
            ->select('user_grades.user_id')
            ->whereNull('user_grades.deleted_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('user_grades as newer_user_grades')
                    ->whereColumn('newer_user_grades.user_id', 'user_grades.user_id')
                    ->whereNull('newer_user_grades.deleted_at')
                    ->where(function ($query): void {
                        $query->whereColumn('newer_user_grades.obtained_at', '>', 'user_grades.obtained_at')
                            ->orWhere(function ($query): void {
                                $query->whereColumn('newer_user_grades.obtained_at', 'user_grades.obtained_at')
                                    ->whereColumn('newer_user_grades.id', '>', 'user_grades.id');
                            });
                    });
            })
            ->where('user_grades.grade_id', $grade->id);

        $users = User::query()
            ->with(['userGrades' => function ($query) use ($grade): void {
                $query->where('grade_id', $grade->id)->latest('obtained_at')->latest('id')->limit(1);
            }])
            ->whereIn('users.id', $activeGradeIds)
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('user_code', 'like', "%{$search}%"));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function userIndex(User $user): AnonymousResourceCollection
    {
        $this->ensureUserOrganization($user);

        return UserGradeResource::collection($user->userGrades()->with('grade')->latest('obtained_at')->latest('id')->paginate(15));
    }

    public function userStore(StoreUserGradeRequest $request, User $user): JsonResponse
    {
        $this->ensureUserOrganization($user);
        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['created_by'] = $request->user()?->id;

        $userGrade = UserGrade::query()->create($data)->load('grade');

        return (new UserGradeResource($userGrade))->response()->setStatusCode(201);
    }

    public function userShow(User $user, UserGrade $userGrade): UserGradeResource
    {
        $this->ensureUserGrade($user, $userGrade);

        return new UserGradeResource($userGrade->load('grade'));
    }

    public function userUpdate(UpdateUserGradeRequest $request, User $user, UserGrade $userGrade): UserGradeResource
    {
        $this->ensureUserGrade($user, $userGrade);
        $userGrade->update($request->validated());

        return new UserGradeResource($userGrade->load('grade'));
    }

    public function userDestroy(User $user, UserGrade $userGrade): JsonResponse
    {
        $this->ensureUserGrade($user, $userGrade);
        $userGrade->delete();

        return response()->json(status: 204);
    }

    private function ensureUserOrganization(User $user): void
    {
        abort_unless((int) $user->organization_id === (int) request()->user()?->organization_id, 404);
    }

    private function ensureUserGrade(User $user, UserGrade $userGrade): void
    {
        $this->ensureUserOrganization($user);
        abort_unless((int) $userGrade->user_id === (int) $user->id && (int) $userGrade->organization_id === (int) $user->organization_id, 404);
    }
}
