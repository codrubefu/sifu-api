<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Jobs\GeneratePersonalDataExport;
use App\Users\Models\ConsentRecord;
use App\Users\Models\GdprExport;
use App\Users\Models\GdprRequest;
use App\Users\Models\User;
use App\Users\Services\GdprErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GdprController extends Controller
{
    public function access(Request $request, ?User $user = null): JsonResponse
    {
        $subject = $user
            ? $this->subject($request, $user)
            : $this->subjectOrChild($request);

        return response()->json([
            'data' => [
                'profile' => $subject->only(['id', 'user_code', 'first_name', 'last_name', 'phone', 'email', 'active', 'created_at', 'updated_at']),
                'consents' => $subject->consentRecords()->orderByDesc('occurred_at')->get(),
            ],
        ]);
    }

    public function export(Request $request, ?User $user = null): JsonResponse
    {
        $subject = $this->subject($request, $user);
        $gdprRequest = GdprRequest::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id,
            'type' => 'export', 'status' => 'processing', 'requested_by' => $request->user()->id,
        ]);
        $export = GdprExport::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id,
            'gdpr_request_id' => $gdprRequest->id, 'status' => 'pending', 'disk' => 'local',
        ]);
        GeneratePersonalDataExport::dispatch($export->id);

        return response()->json(['data' => $export], 202);
    }

    public function exportStatus(Request $request, GdprExport $export): JsonResponse
    {
        abort_unless($export->organization_id === $request->user()->organization_id, 404);
        abort_unless($export->user_id === $request->user()->id || $request->user()->hasRight('gdpr.export'), 403);

        return response()->json(['data' => [
            'id' => $export->id, 'status' => $export->status, 'expires_at' => $export->expires_at,
            'download_url' => $export->status === 'ready' && $export->expires_at?->isFuture()
                ? URL::temporarySignedRoute('gdpr.exports.download', $export->expires_at, ['export' => $export->id]) : null,
        ]]);
    }

    public function download(Request $request, GdprExport $export): StreamedResponse
    {
        abort_unless($request->hasValidSignature() && $export->status === 'ready' && $export->expires_at?->isFuture(), 403);
        abort_unless($export->organization_id === $request->user()->organization_id, 404);
        abort_unless($export->user_id === $request->user()->id || $request->user()->hasRight('gdpr.export'), 403);

        return Storage::disk($export->disk)->download($export->path, 'personal-data.json');
    }

    public function rectify(Request $request, ?User $user = null): JsonResponse
    {
        $subject = $this->subject($request, $user);
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'], 'last_name' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'phone')->where('organization_id', $subject->organization_id)->ignore($subject->id),
            ],
            'email' => ['sometimes', 'email', Rule::unique('users')->where('organization_id', $subject->organization_id)->ignore($subject->id)],
        ]);
        $subject->update($data);
        GdprRequest::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id, 'type' => 'rectification',
            'status' => 'completed', 'requested_by' => $request->user()->id, 'processed_by' => $request->user()->id, 'processed_at' => now(),
            'execution_proof' => ['fields' => array_keys($data)],
        ]);

        return response()->json(['data' => $subject->fresh()]);
    }

    public function consent(Request $request, ?User $user = null): JsonResponse
    {
        $subject = $this->subject($request, $user);
        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:100'], 'channel' => ['required', Rule::in(['sms', 'mail', 'push'])],
            'policy_version' => ['required', 'string', 'max:40'], 'granted' => ['required', 'boolean'], 'source' => ['sometimes', 'string', 'max:64'],
        ]);
        $record = ConsentRecord::query()->create($data + [
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id,
            'actor_id' => $request->user()->id, 'occurred_at' => now(),
            'source' => $data['source'] ?? ($user ? 'administrative' : 'self_service'),
        ]);

        return response()->json(['data' => $record], 201);
    }

    public function requestErasure(Request $request, ?User $user = null): JsonResponse
    {
        $subject = $this->subject($request, $user);
        $gdprRequest = GdprRequest::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id, 'type' => 'erasure',
            'status' => 'pending', 'requested_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $gdprRequest], 202);
    }

    public function process(Request $request, GdprRequest $gdprRequest, GdprErasureService $service): JsonResponse
    {
        abort_unless($gdprRequest->organization_id === $request->user()->organization_id, 404);
        abort_unless($gdprRequest->type === 'erasure' && $gdprRequest->status === 'pending', 422);
        $subject = User::query()->withoutGlobalScopes()->where('organization_id', $gdprRequest->organization_id)->findOrFail($gdprRequest->user_id);
        abort_if($subject->is($request->user()), 422, 'You cannot process your own erasure request.');
        $service->execute($gdprRequest, $subject, $request->user());

        return response()->json(['data' => $gdprRequest->fresh()]);
    }

    private function subject(Request $request, ?User $user): User
    {
        $subject = $user ?? $request->user();
        abort_unless($subject->organization_id === $request->user()->organization_id, 404);

        return $subject;
    }

    private function subjectOrChild(Request $request): User
    {
        $childId = $request->query('child_id');

        if (blank($childId)) {
            return $request->user();
        }

        return User::query()
            ->where('id', (int) $childId)
            ->where('parent_user_id', $request->user()->id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();
    }
}
