<?php

namespace Tests\Feature;

use App\CustomFields\Services\CustomFieldDefinitionService;
use App\Notifications\Events\NotificationRequested;
use App\Service\Models\Service;
use App\Users\Models\Organization;
use App\Users\Models\User;
use App\Users\Services\BearerTokenService;
use Database\Seeders\DemoOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DemoOrganizationResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_all_demo_sections_without_outbound_messages(): void
    {
        Event::fake([NotificationRequested::class]);
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->assertSame(0, Artisan::call('demo:reset'));
        $organization = Organization::where('is_demo', true)->sole();
        $this->assertSame('demo', $organization->slug);
        $this->assertSame('pro', DB::table('organization_plans')->where('id', $organization->plan_id)->value('code'));
        $this->assertSame(10, DB::table('users')->where('organization_id', $organization->id)->count());
        foreach (['services', 'events', 'articles', 'custom_fields', 'payments', 'campaigns', 'segments'] as $table) {
            $count = DB::table($table)->where('organization_id', $organization->id)->count();
            $this->assertGreaterThan(0, $count, $table);
            $this->assertLessThanOrEqual(10, $count, $table);
        }
        $this->assertDatabaseCount('locations', 2);
        $this->assertDatabaseCount('event_occurrences', 9);
        $this->assertDatabaseCount('event_occurrence_user', 6);
        $this->assertDatabaseCount('custom_field_values', 9);
        $this->assertDatabaseCount('sms_messages', 3);
        $this->assertDatabaseCount('notification_deliveries', 3);
        $this->assertSame(6, DB::table('audit_logs')->where('event_type', 'checkin.accepted')->count());
        $this->assertSame(6, DB::table('service_user')->where('status', 'active')->count());
        $this->assertSame(0, DB::table('campaigns')->where('status', '!=', 'sent')->count());
        $admin = User::findOrFail($organization->demo_admin_user_id);
        foreach (['users.manage', 'services.manage', 'events.manage', 'reports.view', 'custom-fields.manage'] as $right) {
            $this->assertTrue($admin->hasRight($right), $right);
        }
        Event::assertNotDispatched(NotificationRequested::class);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_reset_restores_shape_and_admin_identity_and_keeps_existing_bearer_token(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        Artisan::call('demo:reset');
        $organization = Organization::where('is_demo', true)->sole();
        $admin = User::findOrFail($organization->demo_admin_user_id);
        $shape = $this->shape();
        $token = app(BearerTokenService::class)->create($admin);
        // Simulate edits independently of normal password-change session revocation.
        DB::table('users')->where('id', $admin->id)->update(['email' => 'changed@example.test', 'password' => Hash::make('changed'), 'active' => false]);
        $admin->groups()->detach();
        $organization->update(['slug' => 'renamed-demo', 'name' => 'Renamed Demo']);
        $visitor = User::factory()->create(['organization_id' => $organization->id, 'email' => 'demo@club.com']);
        $extra = Service::create(['organization_id' => $organization->id, 'name' => 'Visitor service', 'price' => 10]);
        $extra->delete();
        DB::table('report_exports')->insert(['id' => 'visitor-report', 'organization_id' => $organization->id, 'requested_by' => $visitor->id, 'format' => 'csv', 'filters' => '{}']);
        $oldFieldIds = app(CustomFieldDefinitionService::class)->forEntityType($organization->id, 'users')->modelKeys();
        DB::table('notification_deliveries')->insert(['user_id' => $admin->id, 'event_type' => 'visitor', 'event_key' => 'visitor', 'channel' => 'mail', 'template' => 'visitor', 'payload' => '{}']);
        $this->assertSame(0, Artisan::call('demo:reset'));
        $this->assertSame($shape, $this->shape());
        $this->assertDatabaseMissing('users', ['id' => $visitor->id]);
        $this->assertDatabaseMissing('services', ['id' => $extra->id]);
        $this->assertDatabaseCount('report_exports', 0);
        $this->assertSame($admin->id, $organization->refresh()->demo_admin_user_id);
        $this->assertTrue(Hash::check('password', $admin->refresh()->password));
        $this->assertTrue($admin->active);
        $this->assertSame('demo@club.com', $admin->email);
        $newFieldIds = app(CustomFieldDefinitionService::class)->forEntityType($organization->id, 'users')->modelKeys();
        $this->assertEmpty(array_intersect($oldFieldIds, $newFieldIds));
        $this->withToken($token)->getJson('/api/me')->assertOk();
        $this->postJson('/api/login', ['organization_id' => $organization->id, 'email' => 'demo@club.com', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_other_organization_data_is_unchanged(): void
    {
        $other = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $other->id, 'email' => 'demo@club.com']);
        $service = Service::create(['organization_id' => $other->id, 'name' => 'Customer service', 'price' => 100]);
        DB::table('segments')->insert(['organization_id' => $other->id, 'created_by' => $user->id, 'name' => 'Customer segment', 'criteria' => '{}']);
        $before = [];
        foreach (['organizations', 'users', 'services', 'segments', 'audit_logs'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get();
        }
        Artisan::call('demo:reset');
        Artisan::call('demo:reset');
        foreach ($before as $table => $rows) {
            $query = DB::table($table);
            $query->whereIn('id', $rows->pluck('id'));
            $this->assertSame($rows->toJson(), $query->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_unflagged_slug_collision_is_refused_without_deleting_customer_data(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $this->assertSame(1, Artisan::call('demo:reset'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertFalse($organization->refresh()->is_demo);
    }

    public function test_multiple_flagged_organizations_are_refused(): void
    {
        foreach (range(1, 2) as $index) {
            $organization = Organization::factory()->create();
            $organization->forceFill(['is_demo' => true])->saveQuietly();
        }
        $this->assertSame(1, Artisan::call('demo:reset'));
        $this->assertDatabaseCount('organizations', 2);
    }

    public function test_seed_failure_rolls_back_the_wipe(): void
    {
        Artisan::call('demo:reset');
        $before = DB::table('services')->orderBy('id')->get()->toJson();
        $this->mock(DemoOrganizationSeeder::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andThrow(new RuntimeException('Seed failed'));
        });
        $this->assertSame(1, Artisan::call('demo:reset'));
        $this->assertSame($before, DB::table('services')->orderBy('id')->get()->toJson());
    }

    public function test_reset_removes_private_files_after_commit_and_preserves_customer_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        Artisan::call('demo:reset');
        $organization = Organization::where('is_demo', true)->sole();
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $disk->put('demo/document.txt', 'demo');
        $disk->put('demo/report.csv', 'demo');
        $disk->put('customer/document.txt', 'customer');
        DB::table('user_documents')->insert([
            'organization_id' => $organization->id, 'user_id' => $organization->demo_admin_user_id,
            'category' => 'other', 'title' => 'Visitor document', 'disk' => 'local', 'path' => 'demo/document.txt',
            'original_name' => 'document.txt', 'mime_type' => 'text/plain', 'extension' => 'txt',
            'size' => 4, 'checksum' => hash('sha256', 'demo'),
        ]);
        DB::table('report_exports')->insert([
            'id' => 'demo-report', 'organization_id' => $organization->id,
            'requested_by' => $organization->demo_admin_user_id, 'format' => 'csv', 'filters' => '{}',
            'path' => 'demo/report.csv', 'status' => 'completed',
        ]);
        Artisan::call('demo:reset');
        $disk->assertMissing('demo/document.txt');
        $disk->assertMissing('demo/report.csv');
        $disk->assertExists('customer/document.txt');
        $this->assertDatabaseCount('user_documents', 0);
        $this->assertDatabaseCount('report_exports', 0);
    }

    public function test_reset_recreates_deleted_admin_and_restores_pro_plan(): void
    {
        Artisan::call('demo:reset');
        $organization = Organization::where('is_demo', true)->sole();
        // Delete the assignments/payments first, as the normal admin is their cashier.
        DB::table('service_user')->delete();
        DB::table('payments')->delete();
        DB::table('campaigns')->delete();
        DB::table('segments')->delete();
        DB::table('users')->where('id', $organization->demo_admin_user_id)->delete();
        $organization->plan_id = DB::table('organization_plans')->where('code', 'start')->value('id');
        $organization->saveQuietly();
        $this->assertSame(0, Artisan::call('demo:reset'));
        $this->assertNotNull($organization->refresh()->demo_admin_user_id);
        $this->assertSame('pro', DB::table('organization_plans')->where('id', $organization->plan_id)->value('code'));
        $this->assertDatabaseCount('users', 10);
    }

    private function shape(): array
    {
        $shape = [];
        foreach (['users', 'groups', 'locations', 'services', 'service_user', 'payments', 'events', 'event_occurrences', 'event_occurrence_user', 'articles', 'custom_fields', 'custom_field_values', 'campaigns', 'segments', 'sms_messages', 'notification_deliveries'] as $table) {
            $shape[$table] = DB::table($table)->count();
        }

        return $shape;
    }
}
