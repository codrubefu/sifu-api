<?php

namespace Tests\Feature;

use App\Users\Models\AuditLog;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_logged_with_actor_and_values(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['groups.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/groups', [
                'name' => 'operators',
                'label' => 'Operators',
                'description' => 'Initial description',
            ])
            ->assertCreated();

        $groupId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Group::class,
            'model_id' => $groupId,
            'action' => 'created',
            'changed_by' => $admin->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/groups/{$groupId}", [
                'label' => 'Dispatch Operators',
            ])
            ->assertOk();

        $updateLog = AuditLog::query()
            ->where('model_type', Group::class)
            ->where('model_id', $groupId)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame(['label' => 'Operators'], $updateLog->old_values);
        $this->assertSame(['label' => 'Dispatch Operators'], $updateLog->new_values);
        $this->assertSame($admin->id, $updateLog->changed_by);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/groups/{$groupId}")
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Group::class,
            'model_id' => $groupId,
            'action' => 'deleted',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_user_activity_is_paginated_filtered_and_ordered_newest_first(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        Carbon::setTestNow('2026-07-31 10:00:00');
        $subject = User::factory()->create(['organization_id' => $admin->organization_id]);
        $logger = app(BusinessActivityLogger::class);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $logger->record(AuditLog::USER_CREATED, $subject, $subject, actor: $admin);
        Carbon::setTestNow('2026-08-02 10:00:00');
        $logger->record(AuditLog::CARD_ISSUED, $subject, $subject, newValues: ['user_code' => 'CARD-1'], actor: $admin);
        Carbon::setTestNow();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$subject->id}/activity?per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        // User creation itself is also audited, so the explicit card event remains newest.
        $this->assertSame(AuditLog::CARD_ISSUED, $response->json('data.0.type'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$subject->id}/activity?type=".AuditLog::USER_CREATED)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_activity_requires_users_view_right(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights([]);
        $subject = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$subject->id}/activity")
            ->assertForbidden();
    }

    public function test_user_activity_is_isolated_between_organizations(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);
        [$otherAdmin] = $this->authenticatedUserWithRights(['users.view']);
        $otherSubject = User::factory()->create(['organization_id' => $otherAdmin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$otherSubject->id}/activity")
            ->assertNotFound();
    }

    public function test_sensitive_values_are_removed_recursively(): void
    {
        $subject = User::factory()->create();
        $log = app(BusinessActivityLogger::class)->record(
            AuditLog::USER_UPDATED,
            $subject,
            $subject,
            newValues: ['email' => 'member@example.test', 'password' => 'secret', 'profile' => ['cnp' => '123', 'city' => 'Cluj']],
        );

        $this->assertSame([
            'email' => 'member@example.test',
            'profile' => ['city' => 'Cluj'],
        ], $log->new_values);
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate([
                'name' => $rightName,
            ], [
                'label' => $rightName,
            ]);

            $group->rights()->attach($right);
        }

        $user->groups()->attach($group);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }
}
