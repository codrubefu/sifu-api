<?php

namespace App\Users\Http\Controllers\Api;

use App\CustomFields\Http\Resources\CustomFieldValueResource;
use App\CustomFields\Services\CustomFieldDefinitionService;
use App\CustomFields\Services\CustomFieldValueService;
use App\Events\Http\Resources\EventOccurrenceResource;
use App\Service\Http\Resources\ServiceResource;
use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\UpdateMePasswordRequest;
use App\Users\Http\Resources\UserDocumentResource;
use App\Users\Http\Resources\UserGradeResource;
use App\Users\Http\Resources\UserResource;
use App\Users\Models\User;
use App\Users\Models\UserDocument;
use App\Users\Models\Scopes\LocationAccessScope;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeController extends Controller
{
    private const CUSTOM_FIELD_ENTITY_TYPE = 'users';

    public function __construct(
        private readonly CustomFieldDefinitionService $customFieldDefinitions,
        private readonly CustomFieldValueService $customFieldValues,
        private readonly OrganizationAccessService $organizationAccess,
    ) {
    }

    private function resolveSubject(Request $request): User
    {
        $childId = $request->query('child_id');

        if (blank($childId)) {
            return $request->user();
        }

        return User::query()
            ->withoutGlobalScope(LocationAccessScope::class)
            ->where('id', (int) $childId)
            ->where('parent_user_id', $request->user()->id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();
    }

    public function show(Request $request): UserResource
    {
        return new UserResource(
            $this->organizationAccess->loadUserAccessRelations($this->resolveSubject($request))
        );
    }

    public function children(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->withoutGlobalScope(LocationAccessScope::class)
                ->where('parent_user_id', $request->user()->id)
                ->where('organization_id', $request->user()->organization_id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
        );
    }

    public function updatePassword(UpdateMePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
            'data' => null,
        ]);
    }

    public function customFields(Request $request): AnonymousResourceCollection
    {
        $user = $this->resolveSubject($request);
        $organizationId = (int) $user->organization_id;
        $fields = $this->customFieldDefinitions->forEntityType($organizationId, self::CUSTOM_FIELD_ENTITY_TYPE);
        $values = $this->customFieldValues
            ->valuesForEntity($organizationId, self::CUSTOM_FIELD_ENTITY_TYPE, (int) $user->id)
            ->keyBy('custom_field_id');

        return CustomFieldValueResource::collection(
            $fields->map(fn ($field) => [
                'field' => $field,
                'value' => $values->get($field->id),
            ])->values()
        );
    }

    public function events(Request $request): AnonymousResourceCollection
    {
        $occurrences = $this->resolveSubject($request)
            ->eventOccurrences()
            ->with('event')
            ->orderByDesc('start_datetime')
            ->paginate($request->integer('per_page', 15));

        return EventOccurrenceResource::collection($occurrences);
    }

    public function services(Request $request): AnonymousResourceCollection
    {
        $services = $this->resolveSubject($request)
            ->services()
            ->orderByDesc('service_user.created_at')
            ->paginate($request->integer('per_page', 15));

        return ServiceResource::collection($services);
    }

    public function grades(Request $request): AnonymousResourceCollection
    {
        $subject = $this->resolveSubject($request);

        return UserGradeResource::collection(
            $subject->userGrades()->with('grade')->latest('obtained_at')->latest('id')->paginate($request->integer('per_page', 15))
        );
    }

    public function documents(Request $request): AnonymousResourceCollection
    {
        $subject = $this->resolveSubject($request);

        $documents = UserDocument::query()
            ->with(['uploader', 'location', 'versions'])
            ->where('user_id', $subject->id)
            ->where('organization_id', $subject->organization_id)
            ->where('status', '!=', UserDocument::STATUS_DELETED)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserDocumentResource::collection($documents);
    }

    public function downloadDocument(Request $request, UserDocument $document): StreamedResponse
    {
        $actor = $request->user();
        $owner = $document->user;

        abort_unless(
            (int) $document->organization_id === (int) $actor->organization_id
                && $document->status !== UserDocument::STATUS_DELETED
                && ((int) $document->user_id === (int) $actor->id || (int) $owner?->parent_user_id === (int) $actor->id),
            403
        );

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
