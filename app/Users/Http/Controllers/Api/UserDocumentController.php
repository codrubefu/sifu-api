<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreUserDocumentRequest;
use App\Users\Http\Requests\UpdateUserDocumentRequest;
use App\Users\Http\Resources\UserDocumentResource;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Models\UserDocument;
use App\Users\Services\AntivirusScanner;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserDocumentController extends Controller
{
    public function __construct(
        private readonly AntivirusScanner $scanner,
        private readonly BusinessActivityLogger $activityLogger,
    ) {
    }

    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        $this->abortIfUserIsNotVisible($user, $request);

        $documents = UserDocument::query()
            ->with(['uploader', 'location', 'versions'])
            ->where('user_id', $user->id)
            ->where('organization_id', $request->user()->organization_id)
            ->where('status', '!=', UserDocument::STATUS_DELETED)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserDocumentResource::collection($documents);
    }

    public function store(StoreUserDocumentRequest $request, User $user): UserDocumentResource
    {
        $this->abortIfUserIsNotVisible($user, $request);
        $this->scanner->scan($request->file('file'));

        [$disk, $path] = $this->storeFile($request, $user);

        $document = $this->withStoredFile($disk, $path, fn (): UserDocument => DB::transaction(function () use ($request, $user, $disk, $path): UserDocument {
            $document = $this->createDocument($request, $user, $disk, $path);
            $this->activityLogger->record(AuditLog::USER_DOCUMENT_UPLOADED, $user, $document, [], [
                'document_id' => $document->id,
                'category' => $document->category,
                'title' => $document->title,
                'original_name' => $document->original_name,
                'size' => $document->size,
            ], $request->user());

            return $document;
        }));

        return new UserDocumentResource($document->load(['uploader', 'location', 'versions']));
    }

    public function replace(UpdateUserDocumentRequest $request, User $user, UserDocument $document): UserDocumentResource
    {
        $this->abortIfUserIsNotVisible($user, $request);
        $this->abortIfDocumentIsNotVisible($document, $user, $request);
        $this->scanner->scan($request->file('file'));

        [$disk, $path] = $this->storeFile($request, $user);

        $replacement = $this->withStoredFile($disk, $path, fn (): UserDocument => DB::transaction(function () use ($request, $user, $document, $disk, $path): UserDocument {
            $replacement = $this->createDocument($request, $user, $disk, $path, $document);
            $document->update(['status' => UserDocument::STATUS_REPLACED]);
            $this->activityLogger->record(AuditLog::USER_DOCUMENT_REPLACED, $user, $replacement, [
                'document_id' => $document->id,
            ], [
                'document_id' => $replacement->id,
                'replaces_document_id' => $document->id,
                'category' => $replacement->category,
                'title' => $replacement->title,
            ], $request->user());

            return $replacement;
        }));

        return new UserDocumentResource($replacement->load(['uploader', 'location', 'versions']));
    }

    public function signedDownloadUrl(Request $request, User $user, UserDocument $document): JsonResponse
    {
        $this->abortIfUserIsNotVisible($user, $request);
        $this->abortIfDocumentIsNotVisible($document, $user, $request);

        return response()->json([
            'data' => [
                'download_url' => URL::temporarySignedRoute(
                    'user-documents.download',
                    now()->addMinutes(10),
                    ['user' => $user->id, 'document' => $document->id],
                ),
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ]);
    }

    public function download(Request $request, User $user, UserDocument $document): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $this->abortIfUserIsNotVisible($user, $request);
        $this->abortIfDocumentIsNotVisible($document, $user, $request);

        $this->activityLogger->record(AuditLog::USER_DOCUMENT_DOWNLOADED, $user, $document, [], [
            'document_id' => $document->id,
            'category' => $document->category,
            'title' => $document->title,
        ], $request->user());

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, User $user, UserDocument $document): JsonResponse
    {
        $this->abortIfUserIsNotVisible($user, $request);
        $this->abortIfDocumentIsNotVisible($document, $user, $request);

        DB::transaction(function () use ($request, $user, $document): void {
            Storage::disk($document->disk)->delete($document->path);
            $document->update(['status' => UserDocument::STATUS_DELETED]);
            $this->activityLogger->record(AuditLog::USER_DOCUMENT_DELETED, $user, $document, [], [
                'document_id' => $document->id,
                'category' => $document->category,
                'title' => $document->title,
            ], $request->user());
        });

        return response()->json(status: 204);
    }

    /**
     * Stores the uploaded file on the configured documents disk before any database write,
     * so a failing remote disk (e.g. Google Drive) never leaves a row without a file.
     *
     * @return array{0: string, 1: string}
     */
    private function storeFile(Request $request, User $user): array
    {
        $file = $request->file('file');
        $disk = (string) config('filesystems.user_documents_disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            $path = $file->storeAs(
                "user-documents/{$user->organization_id}/{$user->id}",
                Str::uuid()->toString().'.'.$extension,
                $disk,
            );
        } catch (Throwable $exception) {
            report($exception);
            $path = false;
        }

        abort_if($path === false, 503, 'Documentul nu a putut fi salvat. Încearcă din nou.');

        return [$disk, $path];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withStoredFile(string $disk, string $path, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable) {
                // Keep the original failure; an orphaned file is less important.
            }

            throw $exception;
        }
    }

    private function createDocument(Request $request, User $user, string $disk, string $path, ?UserDocument $replaces = null): UserDocument
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return UserDocument::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'location_id' => $request->input('location_id'),
            'uploaded_by' => $request->user()?->id,
            'replaces_document_id' => $replaces?->id,
            'category' => $request->input('category', $replaces?->category),
            'title' => $request->input('title', $replaces?->title ?? $file->getClientOriginalName()),
            'description' => $request->input('description', $replaces?->description),
            'expires_at' => $request->input('expires_at', $replaces?->expires_at?->toDateString()),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'status' => UserDocument::STATUS_ACTIVE,
            'scanned_at' => now(),
        ]);
    }

    private function abortIfUserIsNotVisible(User $user, Request $request): void
    {
        abort_unless((int) $user->organization_id === (int) $request->user()->organization_id, 404);
        abort_unless(User::query()->whereKey($user->getKey())->exists(), 404);
    }

    private function abortIfDocumentIsNotVisible(UserDocument $document, User $user, Request $request): void
    {
        abort_unless((int) $document->organization_id === (int) $request->user()->organization_id, 404);
        abort_unless((int) $document->user_id === (int) $user->id, 404);
        abort_unless($document->status !== UserDocument::STATUS_DELETED, 404);
    }
}
