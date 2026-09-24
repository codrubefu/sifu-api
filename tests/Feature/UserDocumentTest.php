<?php

namespace Tests\Feature;

use App\Users\Models\AuditLog;
use App\Users\Models\GdprRequest;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Models\UserDocument;
use App\Users\Services\BearerTokenService;
use App\Users\Services\GdprErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Masbug\Flysystem\GoogleDriveAdapter;
use Tests\TestCase;

class UserDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_document_lifecycle_uses_private_storage_and_audit(): void
    {
        Storage::fake('local');
        [$operator, $token] = $this->authenticatedUserWithRights(['user-documents.view', 'user-documents.upload', 'user-documents.delete']);
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/users/{$member->id}/documents", [
                'file' => UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
                'category' => 'contract',
                'title' => 'Contract semnat',
                'description' => 'Contract initial',
                'expires_at' => '2027-01-01',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'contract')
            ->assertJsonPath('data.title', 'Contract semnat');

        $document = UserDocument::query()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::USER_DOCUMENT_UPLOADED]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$member->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.0.id', $document->id);

        $downloadUrl = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/users/{$member->id}/documents/{$document->id}/download-url")
            ->assertOk()
            ->json('data.download_url');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get($downloadUrl)
            ->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::USER_DOCUMENT_DOWNLOADED]);

        $replacement = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/users/{$member->id}/documents/{$document->id}/replace", [
                'file' => UploadedFile::fake()->create('contract-v2.pdf', 64, 'application/pdf'),
                'category' => 'contract',
                'title' => 'Contract actualizat',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('user_documents', ['id' => $document->id, 'status' => UserDocument::STATUS_REPLACED]);
        $this->assertDatabaseHas('user_documents', ['id' => $replacement, 'replaces_document_id' => $document->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/users/{$member->id}/documents/{$replacement}")
            ->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::USER_DOCUMENT_DELETED]);
    }

    public function test_user_document_upload_uses_configured_disk(): void
    {
        Storage::fake('local');
        Storage::fake('google');
        config(['filesystems.user_documents_disk' => 'google']);
        [$operator, $token] = $this->authenticatedUserWithRights(['user-documents.view', 'user-documents.upload', 'user-documents.delete']);
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/users/{$member->id}/documents", [
                'file' => UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
                'category' => 'contract',
                'title' => 'Contract',
            ])
            ->assertCreated();

        $document = UserDocument::query()->firstOrFail();
        $this->assertSame('google', $document->disk);
        Storage::disk('google')->assertExists($document->path);
        Storage::disk('local')->assertMissing($document->path);

        $downloadUrl = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/users/{$member->id}/documents/{$document->id}/download-url")
            ->json('data.download_url');
        $this->withHeader('Authorization', "Bearer {$token}")->get($downloadUrl)->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/users/{$member->id}/documents/{$document->id}")
            ->assertNoContent();
        Storage::disk('google')->assertMissing($document->path);
    }

    public function test_user_document_upload_failure_on_disk_does_not_create_document(): void
    {
        config([
            'filesystems.disks.broken' => ['driver' => 'local', 'root' => '/dev/null/documents', 'throw' => true],
            'filesystems.user_documents_disk' => 'broken',
        ]);
        [$operator, $token] = $this->authenticatedUserWithRights(['user-documents.upload']);
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/users/{$member->id}/documents", [
                'file' => UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
                'category' => 'contract',
                'title' => 'Contract',
            ], ['Accept' => 'application/json'])
            ->assertStatus(503);

        $this->assertDatabaseCount('user_documents', 0);
        $this->assertDatabaseMissing('audit_logs', ['event_type' => AuditLog::USER_DOCUMENT_UPLOADED]);
    }

    public function test_google_drive_disk_is_registered(): void
    {
        $keyPath = tempnam(sys_get_temp_dir(), 'gdrive');
        file_put_contents($keyPath, json_encode([
            'type' => 'service_account',
            'client_email' => 'sifu@example.iam.gserviceaccount.com',
            'client_id' => '1',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nkey\n-----END PRIVATE KEY-----\n",
        ]));
        config([
            'filesystems.disks.google.service_account_json' => $keyPath,
            'filesystems.disks.google.folder' => null,
        ]);

        try {
            $this->assertInstanceOf(GoogleDriveAdapter::class, Storage::disk('google')->getAdapter());
        } finally {
            unlink($keyPath);
        }
    }

    public function test_gdpr_erasure_deletes_user_document_files(): void
    {
        Storage::fake('local');
        [$operator] = $this->authenticatedUserWithRights(['gdpr.process']);
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $path = 'user-documents/'.$operator->organization_id.'/'.$member->id.'/id.pdf';
        Storage::disk('local')->put($path, 'content');
        $document = UserDocument::query()->create([
            'organization_id' => $operator->organization_id,
            'user_id' => $member->id,
            'uploaded_by' => $operator->id,
            'category' => 'identity_document',
            'title' => 'CI',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 7,
            'checksum' => hash('sha256', 'content'),
        ]);
        $request = GdprRequest::query()->create([
            'organization_id' => $operator->organization_id,
            'user_id' => $member->id,
            'type' => 'erasure',
            'status' => 'pending',
            'requested_by' => $operator->id,
        ]);

        app(GdprErasureService::class)->execute($request, $member, $operator);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('user_documents', [
            'id' => $document->id,
            'status' => UserDocument::STATUS_DELETED,
            'path' => 'gdpr-erased',
        ]);
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Test Group']);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate(['name' => $rightName], ['label' => $rightName]);
            $group->rights()->attach($right);
        }

        $user->groups()->attach($group);

        return [$user, app(BearerTokenService::class)->create($user)];
    }
}
