<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Payments\Models\Payment;
use App\Users\Mail\PasswordSetupMail;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_users(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $admin->id])
            ->assertJsonFragment(['email' => 'jane@example.com']);
    }

    public function test_users_are_listed_alphabetically_by_default(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Zoe',
            'last_name' => 'Alpha',
            'email' => 'sort-user-alpha-zoe@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Adam',
            'last_name' => 'Bravo',
            'email' => 'sort-user-bravo-adam@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Adam',
            'last_name' => 'Alpha',
            'email' => 'sort-user-alpha-adam@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users?search=sort-user')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'sort-user-alpha-adam@example.com')
            ->assertJsonPath('data.1.email', 'sort-user-alpha-zoe@example.com')
            ->assertJsonPath('data.2.email', 'sort-user-bravo-adam@example.com');
    }

    public function test_user_with_locations_only_sees_users_from_shared_locations(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $allowedLocation = Location::query()->create([
            'name' => 'HQ',
            'description' => 'Main office',
        ]);
        $otherLocation = Location::query()->create([
            'name' => 'Remote',
            'description' => 'Remote office',
        ]);
        $visibleUser = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Visible',
            'email' => 'visible@example.com',
        ]);
        $hiddenUser = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Hidden',
            'email' => 'hidden@example.com',
        ]);

        $admin->locations()->attach($allowedLocation);
        $visibleUser->locations()->attach($allowedLocation);
        $hiddenUser->locations()->attach($otherLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'visible@example.com'])
            ->assertJsonMissing(['email' => 'hidden@example.com']);
    }

    public function test_user_can_search_users_by_partial_user_code(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => 'USR11100000000000000000000000001',
            'first_name' => 'Matching',
            'email' => 'matching-code@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => 'USR22200000000000000000000000002',
            'first_name' => 'Other',
            'email' => 'other-code@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users?search=111')
            ->assertOk()
            ->assertJsonFragment(['email' => 'matching-code@example.com'])
            ->assertJsonMissing(['email' => 'other-code@example.com']);
    }

    public function test_user_code_search_endpoint_only_searches_user_code(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);

        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => 'USR11100000000000000000000000001',
            'first_name' => 'Matching',
            'email' => 'matching-code@example.com',
        ]);
        User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => 'USR22200000000000000000000000002',
            'first_name' => '111',
            'email' => '111@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users/search/user-code?search=111')
            ->assertOk()
            ->assertJsonFragment(['email' => 'matching-code@example.com'])
            ->assertJsonMissing(['email' => '111@example.com']);
    }

    public function test_user_code_search_endpoint_ignores_location_scope_and_returns_user_code(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $allowedLocation = Location::query()->create([
            'name' => 'Allowed',
            'description' => 'Allowed location',
        ]);
        $otherLocation = Location::query()->create([
            'name' => 'Other',
            'description' => 'Other location',
        ]);
        $targetUser = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'user_code' => '323',
            'first_name' => 'Mathias',
            'email' => 'mathias323@example.com',
        ]);

        $admin->locations()->attach($allowedLocation);
        $targetUser->locations()->attach($otherLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users/search/user-code?search=323')
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'mathias323@example.com',
                'user_code' => '323',
            ]);
    }

    public function test_clients_endpoint_lists_users_with_only_profile_view_or_no_rights(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $client = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'client@example.com']);
        $administrator = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'administrator@example.com']);
        $withoutRights = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'without-rights@example.com']);

        $this->attachRightsToUser($client, ['profile.view']);
        $this->attachRightsToUser($administrator, ['profile.view', 'users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients')
            ->assertOk()
            ->assertJsonFragment(['email' => 'client@example.com'])
            ->assertJsonFragment(['email' => 'without-rights@example.com'])
            ->assertJsonMissing(['email' => 'administrator@example.com']);
    }

    public function test_clients_endpoint_includes_parent_user_for_list_display(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $parent = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Parent',
            'last_name' => 'Tutor',
            'email' => 'parent@example.com',
        ]);
        $child = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'parent_user_id' => $parent->id,
            'first_name' => 'Child',
            'last_name' => 'Member',
            'email' => null,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $child->id,
                'parent_user_id' => $parent->id,
            ])
            ->assertJsonPath('data.0.parent.first_name', 'Parent')
            ->assertJsonPath('data.0.parent.last_name', 'Tutor');
    }

    public function test_administrators_endpoint_excludes_users_with_only_profile_view_right_and_without_groups(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $client = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'client@example.com']);
        $administrator = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'administrator@example.com']);
        $withoutRights = User::factory()->create(['organization_id' => $admin->organization_id, 'email' => 'without-rights@example.com']);

        $this->attachRightsToUser($client, ['profile.view']);
        $this->attachRightsToUser($administrator, ['profile.view', 'users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/administrators')
            ->assertOk()
            ->assertJsonMissing(['email' => 'client@example.com'])
            ->assertJsonFragment(['email' => 'administrator@example.com'])
            ->assertJsonMissing(['email' => 'without-rights@example.com']);
    }

    public function test_user_with_manage_right_can_create_user_with_groups(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'user_code' => 'USR00000000000000000000000000001',
                'first_name' => 'New',
                'last_name' => 'User',
                'phone' => '+15550001111',
                'active' => true,
                'email' => 'new@example.com',
                'password' => 'password',
                'group_ids' => [$group->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_code', 'USR00000000000000000000000000001')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.groups.0.id', $group->id);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'user_code' => 'USR00000000000000000000000000001',
        ]);
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id]);
    }

    public function test_user_with_manage_right_can_create_user_without_password(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'No',
                'last_name' => 'Password',
                'email' => 'no-password@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'no-password@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'email' => 'no-password@example.com',
            'password' => null,
        ]);
    }

    public function test_creating_user_sends_password_setup_email(): void
    {
        Mail::fake();

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => 'password-setup@example.com',
                'password' => 'Xk9#mQ2vLp7wnR4z',
            ])
            ->assertCreated();

        Mail::assertQueued(PasswordSetupMail::class, function (PasswordSetupMail $mail) {
            return $mail->hasTo('password-setup@example.com') && $mail->isNewAccount === true;
        });
    }

    public function test_user_email_must_be_unique_within_same_organization(): void
    {
        $organization = Organization::query()->create(['name' => 'Same Org', 'slug' => 'same-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $organization->id]);

        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'shared@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Duplicate',
                'last_name' => 'Email',
                'email' => 'shared@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_email_can_be_reused_in_different_organizations(): void
    {
        $firstOrganization = Organization::query()->create(['name' => 'First Org', 'slug' => 'first-org']);
        $secondOrganization = Organization::query()->create(['name' => 'Second Org', 'slug' => 'second-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $secondOrganization->id]);

        User::factory()->create([
            'organization_id' => $firstOrganization->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Shared',
                'last_name' => 'Email',
                'email' => 'shared@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'shared@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'organization_id' => $secondOrganization->id,
            'email' => 'shared@example.com',
        ]);
    }

    public function test_user_code_and_phone_must_be_unique_within_same_organization(): void
    {
        $organization = Organization::query()->create(['name' => 'Unique Codes Org', 'slug' => 'unique-codes-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $organization->id]);

        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'existing-unique@example.com',
            'user_code' => 'CARD-001',
            'phone' => '+40722000001',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Duplicate',
                'last_name' => 'Identifiers',
                'email' => 'duplicate-identifiers@example.com',
                'user_code' => 'CARD-001',
                'phone' => '+40722000001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_code', 'phone']);
    }

    public function test_user_code_and_phone_can_be_reused_in_different_organizations(): void
    {
        $firstOrganization = Organization::query()->create(['name' => 'First Identifiers Org', 'slug' => 'first-identifiers-org']);
        $secondOrganization = Organization::query()->create(['name' => 'Second Identifiers Org', 'slug' => 'second-identifiers-org']);
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $admin->update(['organization_id' => $secondOrganization->id]);

        User::factory()->create([
            'organization_id' => $firstOrganization->id,
            'email' => 'first-identifiers@example.com',
            'user_code' => 'CARD-001',
            'phone' => '+40722000001',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Shared',
                'last_name' => 'Identifiers',
                'email' => 'second-identifiers@example.com',
                'user_code' => 'CARD-001',
                'phone' => '+40722000001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_code', 'CARD-001')
            ->assertJsonPath('data.phone', '+40722000001');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'organization_id' => $secondOrganization->id,
            'user_code' => 'CARD-001',
            'phone' => '+40722000001',
        ]);
    }

    public function test_user_can_have_multiple_active_services(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $services = [
            Service::query()->create($this->serviceData([
                'name' => 'Basic',
                'is_active' => true,
            ])),
            Service::query()->create($this->serviceData([
                'name' => 'Pro',
                'is_active' => true,
            ])),
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Subscribed',
                'last_name' => 'User',
                'email' => 'subscribed@example.com',
                'password' => 'password',
                'service_ids' => collect($services)->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.services')
            ->assertJsonCount(2, 'data.active_services');

        $userId = $response->json('data.id');

        foreach ($services as $service) {
            $this->assertDatabaseHas('service_user', [
                'service_id' => $service->id,
                'user_id' => $userId,
            ]);
        }
    }

    public function test_user_service_dates_are_stored_and_expiration_is_calculated(): void
    {
        Carbon::setTestNow('2026-05-18 10:00:00');

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Monthly',
            'duration_days' => 10,
            'is_active' => true,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Subscribed',
                'last_name' => 'History',
                'email' => 'history@example.com',
                'password' => 'password',
                'services' => [
                    [
                        'id' => $service->id,
                        'start_date' => '2026-05-15',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_active_service', true)
            ->assertJsonPath('data.service_history.0.start_date', '2026-05-15')
            ->assertJsonPath('data.service_history.0.expires_at', '2026-05-25')
            ->assertJsonPath('data.service_history.0.is_active', true);

        $this->assertDatabaseHas('service_user', [
            'service_id' => $service->id,
            'user_id' => $response->json('data.id'),
            'start_date' => '2026-05-15',
            'expires_at' => '2026-05-25',
        ]);

        Carbon::setTestNow();
    }

    public function test_assigning_service_creates_bill_without_invoice(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Bill only',
            'is_active' => true,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Bill',
                'last_name' => 'Only',
                'email' => 'bill-only@example.com',
                'password' => 'password',
                'service_ids' => [$service->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.service_history.0.invoice_number', null)
            ->assertJsonPath('data.service_history.0.bill_number', 'BILL000001');

        $this->assertDatabaseHas('service_user', [
            'service_id' => $service->id,
            'user_id' => $response->json('data.id'),
            'invoice_number' => null,
            'bill_number' => 'BILL000001',
        ]);
    }

    public function test_expired_user_service_is_not_marked_active(): void
    {
        Carbon::setTestNow('2026-05-18 10:00:00');

        [, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Expired',
            'duration_days' => 5,
            'is_active' => true,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Expired',
                'last_name' => 'User',
                'email' => 'expired-service@example.com',
                'password' => 'password',
                'services' => [
                    [
                        'id' => $service->id,
                        'start_date' => '2026-05-01',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_active_service', false)
            ->assertJsonCount(0, 'data.active_services')
            ->assertJsonPath('data.service_history.0.is_active', false);

        Carbon::setTestNow();
    }

    public function test_user_with_manage_right_can_update_user(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'email' => 'old@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'user_code' => 'USR00000000000000000000000000002',
                'first_name' => 'Updated',
                'email' => 'updated@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.user_code', 'USR00000000000000000000000000002')
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.email', 'updated@example.com');
    }

    public function test_user_with_locations_cannot_update_user_from_other_location(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $allowedLocation = Location::query()->create([
            'name' => 'Allowed',
            'description' => 'Allowed location',
        ]);
        $blockedLocation = Location::query()->create([
            'name' => 'Blocked',
            'description' => 'Blocked location',
        ]);
        $user = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'email' => 'blocked-update@example.com',
        ]);

        $admin->locations()->attach($allowedLocation);
        $user->locations()->attach($blockedLocation);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/{$user->id}", [
                'first_name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_user_with_manage_right_can_sync_services_through_dedicated_route(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $oldService = Service::query()->create($this->serviceData([
            'name' => 'Legacy',
            'is_active' => true,
        ]));
        $newService = Service::query()->create($this->serviceData([
            'name' => 'Fresh',
            'is_active' => true,
        ]));

        $user->services()->attach($oldService);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'service_ids' => [$newService->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $newService->id)
            ->assertJsonCount(1, 'data.active_services');

        $this->assertDatabaseHas('service_user', [
            'service_id' => $newService->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('service_user', [
            'service_id' => $oldService->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_with_manage_right_can_remove_all_services_through_dedicated_route(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Legacy',
            'is_active' => true,
        ]));

        $user->services()->attach($service);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'services' => [],
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data.services')
            ->assertJsonCount(0, 'data.active_services');

        $this->assertDatabaseMissing('service_user', [
            'service_id' => $service->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_with_manage_right_can_remove_omitted_service_through_dedicated_route(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $serviceToKeepA = Service::query()->create($this->serviceData([
            'name' => 'Keep A',
            'is_active' => true,
        ]));
        $serviceToRemove = Service::query()->create($this->serviceData([
            'name' => 'Remove',
            'is_active' => true,
        ]));
        $serviceToKeepB = Service::query()->create($this->serviceData([
            'name' => 'Keep B',
            'is_active' => true,
        ]));

        $user->services()->attach($serviceToKeepA, ['start_date' => '2026-08-30']);
        $user->services()->attach($serviceToRemove, ['start_date' => '2026-08-25']);
        $user->services()->attach($serviceToKeepB, ['start_date' => '2026-08-22']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'services' => [
                    ['id' => $serviceToKeepA->id, 'start_date' => '2026-08-30'],
                    ['id' => $serviceToKeepB->id, 'start_date' => '2026-08-22'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.services')
            ->assertJsonMissing(['id' => $serviceToRemove->id]);

        $this->assertDatabaseHas('service_user', [
            'service_id' => $serviceToKeepA->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('service_user', [
            'service_id' => $serviceToKeepB->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('service_user', [
            'service_id' => $serviceToRemove->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_syncing_existing_service_preserves_lifecycle_and_payment_link(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Paid plan',
            'price' => 100,
            'duration_days' => 30,
            'expiration_rule' => 'duration',
        ]));

        $user->services()->attach($service, [
            'status' => 'active',
            'start_date' => '2026-08-01',
            'expires_at' => '2026-08-31',
            'activated_at' => '2026-08-01 10:00:00',
        ]);
        $assignmentId = $user->services()->whereKey($service->id)->first()->pivot->id;
        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CARD,
            'status' => Payment::STATUS_CONFIRMED,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignmentId,
            'amount' => 100,
            'paid_at' => '2026-08-01 10:00:00',
            'admin_id' => $admin->id,
        ]);
        DB::table('service_user')->where('id', $assignmentId)->update(['activation_payment_id' => $payment->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/users/service/{$user->id}", [
                'services' => [
                    ['id' => $service->id, 'start_date' => '2026-08-02'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.services.0.pivot.status', 'active')
            ->assertJsonPath('data.services.0.pivot.activation_payment_id', $payment->id);

        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'active',
            'activation_payment_id' => $payment->id,
        ]);
    }

    public function test_service_history_uses_lifecycle_status(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.view']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Paused plan',
            'price' => 0,
            'duration_days' => 30,
            'expiration_rule' => 'duration',
        ]));

        $user->services()->attach($service, [
            'status' => 'suspended',
            'start_date' => now()->subDays(5)->toDateString(),
            'expires_at' => now()->addDays(25)->toDateString(),
            'suspended_at' => now(),
            'status_reason' => 'Medical leave',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.service_history.0.service_id', $service->id)
            ->assertJsonPath('data.service_history.0.status', 'suspended')
            ->assertJsonPath('data.service_history.0.is_active', false)
            ->assertJsonPath('data.service_history.0.status_reason', 'Medical leave');
    }

    public function test_user_with_manage_right_can_delete_user(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['users.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_without_manage_right_cannot_create_user(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['users.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users', [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'email' => 'blocked@example.com',
                'password' => 'password',
            ])
            ->assertForbidden();
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $this->attachRightsToUser($user, $rightNames);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }

    private function attachRightsToUser(User $user, array $rightNames): void
    {
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
    }

    private function serviceData(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Test service',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
    }
}
