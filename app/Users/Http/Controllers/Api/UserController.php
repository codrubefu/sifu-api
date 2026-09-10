<?php

namespace App\Users\Http\Controllers\Api;

use App\Notifications\Events\NotificationRequested;
use App\Service\Models\Service;
use App\Service\Services\ServiceDocumentSequenceService;
use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\SyncUserServicesRequest;
use App\Users\Http\Requests\StoreUserRequest;
use App\Users\Http\Requests\UpdateUserRequest;
use App\Users\Http\Resources\ActivityResource;
use App\Events\Http\Resources\EventOccurrenceResource;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\AuditLog;
use App\Users\Models\Scopes\LocationAccessScope;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use App\Users\Services\OrganizationAccessService;
use App\Users\Models\GdprRequest;
use App\Users\Services\GdprErasureService;
use App\Users\Mail\PasswordSetupMail;
use App\Users\Services\PasswordSetupTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function __construct(
        private readonly OrganizationAccessService $organizationAccess,
        private readonly BusinessActivityLogger $activityLogger,
        private readonly GdprErasureService $gdprErasureService,
        private readonly ServiceDocumentSequenceService $documentSequences,
        private readonly PasswordSetupTokenService $passwordSetupTokens,
    )
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request);
    }

    public function administrators(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request, hasGroups: true, exceptOnlyRight: 'profile.view');
    }

    public function clients(Request $request): AnonymousResourceCollection
    {
        return $this->userList($request, onlyRight: 'profile.view');
    }

    public function searchByUserCode(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->withoutGlobalScope(LocationAccessScope::class)
            ->with($this->userRelationsForResponse($request->user()?->organization_id))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where('user_code', 'like', "%{$search}%");
            })
            ->orderBy('user_code', 'asc')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    private function userList(
        Request $request,
        ?bool $hasGroups = null,
        ?string $onlyRight = null,
        ?string $exceptOnlyRight = null,
    ): AnonymousResourceCollection
    {
        $users = User::query()
            ->with($this->userRelationsForResponse($request->user()?->organization_id))
            ->when($hasGroups === true, fn ($query) => $query->has('groups'))
            ->when($hasGroups === false, fn ($query) => $query->doesntHave('groups'))
            ->when($onlyRight !== null, function ($query) use ($onlyRight): void {
                $query->where(function ($query) use ($onlyRight): void {
                    $query->whereDoesntHave('groups.rights')
                        ->orWhere(function ($query) use ($onlyRight): void {
                            $query->whereHas('groups.rights', fn ($query) => $query->where('name', $onlyRight))
                                ->whereDoesntHave('groups.rights', fn ($query) => $query->where('name', '!=', $onlyRight));
                        });
                });
            })
            ->when($exceptOnlyRight !== null, function ($query) use ($exceptOnlyRight): void {
                $query->where(function ($query) use ($exceptOnlyRight): void {
                    $query->whereDoesntHave('groups.rights', fn ($query) => $query->where('name', $exceptOnlyRight))
                        ->orWhereHas('groups.rights', fn ($query) => $query->where('name', '!=', $exceptOnlyRight));
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
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $groupIds = $data['group_ids'] ?? [];
        $locationIds = $data['location_ids'] ?? [];
        $serviceAssignments = $this->serviceAssignments($data);
        unset($data['group_ids']);
        unset($data['location_ids']);
        unset($data['service_ids']);
        unset($data['services']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        $user = DB::transaction(function () use ($data, $groupIds, $locationIds, $serviceAssignments): User {
            $user = User::query()->create($data);
            $user->groups()->sync($groupIds);
            $user->locations()->sync($locationIds);
            $this->attachServiceAssignments($user, $serviceAssignments);

            return $user;
        });

        if (filled($user->email)) {
            $this->sendPasswordSetupEmail($user);
        }

        return (new UserResource($this->loadUserForResponse($user, $request->user()?->organization_id, true)))
            ->response()
            ->setStatusCode(201);
    }

    private function sendPasswordSetupEmail(User $user): void
    {
        $token = $this->passwordSetupTokens->generate($user);

        Mail::to($user->email)->queue(new PasswordSetupMail($user, $token, isNewAccount: true));
    }

    public function show(User $user): UserResource
    {
        $this->abortIfUserIsNotVisible($user);

        return new UserResource($this->loadUserForResponse($user, request()->user()?->organization_id, true));
    }

    public function events(Request $request, User $user): AnonymousResourceCollection
    {
        $this->abortIfUserIsNotVisible($user);

        return EventOccurrenceResource::collection(
            $user->eventOccurrences()
                ->with(['event.category', 'event.requiredService'])
                ->orderByDesc('start_datetime')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->abortIfUserIsNotVisible($user);

        $data = $request->validated();
        $groupIds = $data['group_ids'] ?? null;
        $locationIds = $data['location_ids'] ?? null;
        $hasServiceAssignments = array_key_exists('service_ids', $data) || array_key_exists('services', $data);
        $serviceAssignments = $this->serviceAssignments($data);
        unset($data['group_ids']);
        unset($data['location_ids']);
        unset($data['service_ids']);
        unset($data['services']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $groupIds, $locationIds, $hasServiceAssignments, $serviceAssignments): void {
            $user->update($data);

            if ($groupIds !== null) {
                $user->groups()->sync($groupIds);
            }

            if ($locationIds !== null) {
                $user->locations()->sync($locationIds);
            }

            if ($hasServiceAssignments) {
                $previous = $user->services()->get()->keyBy('id');
                $assignedIds = collect($serviceAssignments)->pluck('id');
                $this->detachMissingServiceAssignments($user, $assignedIds->all());
                $this->attachServiceAssignments($user, $serviceAssignments, $previous);
                $previous->except($assignedIds->all())->each(function (Service $service) use ($user): void {
                    $this->activityLogger->record(AuditLog::SERVICE_SUSPENDED, $user, $service, [], [
                        'service_id' => $service->id,
                    ]);
                });
            }
        });

        return new UserResource($this->loadUserForResponse($user, $request->user()?->organization_id, true));
    }

    public function syncServices(SyncUserServicesRequest $request, User $user): UserResource
    {
        $this->abortIfUserIsNotVisible($user);

        DB::transaction(function () use ($request, $user): void {
            $previous = $user->services()->get()->keyBy('id');
            $assignments = $this->serviceAssignments($request->validated());
            $assignedIds = collect($assignments)->pluck('id');
            $this->detachMissingServiceAssignments($user, $assignedIds->all());
            $this->attachServiceAssignments($user, $assignments, $previous);

            $previous->except($assignedIds->all())->each(function (Service $service) use ($user): void {
                $this->activityLogger->record(AuditLog::SERVICE_SUSPENDED, $user, $service, [], [
                    'service_id' => $service->id,
                ]);
            });
        });

        return new UserResource($this->loadUserForResponse($user, request()->user()?->organization_id, true));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->abortIfUserIsNotVisible($user);

        if ($request->user()?->is($user)) {
            return response()->json([
                'message' => 'You cannot delete your own user account.',
            ], 422);
        }

        if ($error = $this->organizationAccess->deleteBlockedByManyToManyResponse(
            $user,
            ['groups', 'locations', 'services', 'eventOccurrences'],
        )) {
            return response()->json($error, 422);
        }

        $gdprRequest = GdprRequest::query()->create([
            'organization_id' => $user->organization_id, 'user_id' => $user->id, 'type' => 'erasure',
            'status' => 'pending', 'requested_by' => $request->user()->id,
        ]);
        $this->gdprErasureService->execute($gdprRequest, $user, $request->user());

        return response()->json(status: 204);
    }

    private function serviceAssignments(array $data): array
    {
        if (array_key_exists('services', $data)) {
            return collect($data['services'])
                ->map(fn (array $service): array => [
                    'id' => $service['id'],
                    'start_date' => $service['start_date'] ?? now()->toDateString(),
                ])
                ->all();
        }

        return collect($data['service_ids'] ?? [])
            ->map(fn (int $serviceId): array => [
                'id' => $serviceId,
                'start_date' => now()->toDateString(),
            ])
            ->all();
    }

    private function userRelationsForResponse(?int $organizationId, bool $includeServices = false): array
    {
        $relations = [
            'parent' => fn ($query) => $query->withoutGlobalScope(LocationAccessScope::class),
            'groups.rights' => fn ($query) => $this->organizationAccess->applyAvailableRightsFilter($query, $organizationId),
            'locations',
            'activeServices',
            'activeUserGrade.grade',
            'userGrades.grade',
        ];

        if ($includeServices) {
            $relations[] = 'services';
        }

        return $relations;
    }

    private function loadUserForResponse(User $user, ?int $organizationId, bool $includeServices = false): User
    {
        return $user->load($this->userRelationsForResponse($organizationId, $includeServices));
    }

    private function abortIfUserIsNotVisible(User $user): void
    {
        abort_unless(User::query()->whereKey($user->getKey())->exists(), 404);
    }

    private function attachServiceAssignments(User $user, array $assignments, mixed $previousServices = []): void
    {
        $previous = collect($previousServices)->keyBy('id');

        foreach ($assignments as $assignment) {
            $service = Service::query()->findOrFail($assignment['id']);
            $startDate = CarbonImmutable::parse($assignment['start_date'])->startOfDay();
            $startDateValue = $startDate->toDateString();
            $expiresAt = $this->serviceExpiresAt($service, $startDate);

            if ($previous->has($service->id)) {
                $previousPivot = $previous->get($service->id)->pivot;
                $user->services()->updateExistingPivot($service->id, [
                    'start_date' => $startDateValue,
                    'expires_at' => $expiresAt,
                ]);

                $previousStartDate = $previousPivot?->start_date === null
                    ? null
                    : CarbonImmutable::parse($previousPivot->start_date)->toDateString();

                if ($previousStartDate !== $startDateValue) {
                    $this->activityLogger->record(AuditLog::SERVICE_RENEWED, $user, $service, [], [
                        'service_id' => $service->id,
                        'start_date' => $startDateValue,
                    ]);
                }

                continue;
            }

            $pivotData = [
                'bill_number' => $this->documentSequences->nextBill((int) $service->organization_id),
                'status' => $this->serviceInitialStatus($service, $startDate),
                'start_date' => $startDateValue,
                'expires_at' => $expiresAt,
                'activated_at' => (float) $service->price > 0 ? null : now(),
            ];

            $user->services()->attach($service->id, $pivotData);

            DB::afterCommit(function () use ($user, $service, $startDate): void {
                NotificationRequested::dispatch(
                    $user->fresh(),
                    NotificationRequested::SERVICE_ACTIVATED,
                    "service.activated:{$user->id}:{$service->id}:{$startDate->toDateString()}",
                    ['service' => $service->name],
                );
            });

            $this->activityLogger->record(
                AuditLog::SERVICE_ASSIGNED,
                $user,
                $service,
                [],
                ['service_id' => $service->id, 'start_date' => $startDate->toDateString()],
            );
        }
    }

    private function detachMissingServiceAssignments(User $user, array $assignedIds): void
    {
        $query = $user->serviceAssignments();

        if ($assignedIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('service_id', $assignedIds)->delete();
    }

    public function activity(Request $request, User $user): AnonymousResourceCollection
    {
        $this->abortIfUserIsNotVisible($user);

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $activities = AuditLog::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('subject_user_id', $user->id)
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return ActivityResource::collection($activities);
    }

    private function serviceExpiresAt(Service $service, CarbonImmutable $startDate): ?string
    {
        return match ($service->expiration_rule) {
            'none' => null,
            'fixed_date' => $service->fixed_expires_at?->toDateString(),
            default => $service->duration_days === null ? null : $startDate->addDays($service->duration_days)->toDateString(),
        };
    }

    private function serviceInitialStatus(Service $service, CarbonImmutable $startDate): string
    {
        if ((float) $service->price > 0) {
            return 'pending';
        }

        return $startDate->isFuture() ? 'reserved' : 'active';
    }
}
