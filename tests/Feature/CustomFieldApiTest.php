<?php

namespace Tests\Feature;

use App\CustomFields\Models\CustomField;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_custom_field_definitions_per_organization_and_entity_type(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/custom-fields', [
                'entity_type' => 'contacts',
                'name' => 'Lifecycle Stage',
                'slug' => 'lifecycle_stage',
                'type' => 'select',
                'options' => [
                    'choices' => [
                        ['label' => 'Lead', 'value' => 'lead'],
                        ['label' => 'Customer', 'value' => 'customer'],
                    ],
                ],
                'validation_rules' => ['string'],
                'is_required' => true,
                'sort_order' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $user->organization_id)
            ->assertJsonPath('data.entity_type', 'contacts')
            ->assertJsonPath('data.slug', 'lifecycle_stage');

        $fieldId = $response->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/custom-fields?entity_type=contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'lifecycle_stage');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/custom-fields/{$fieldId}", [
                'name' => 'Updated Stage',
                'sort_order' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Stage')
            ->assertJsonPath('data.sort_order', 5);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/custom-fields/{$fieldId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('custom_fields', ['id' => $fieldId]);
    }

    public function test_user_can_save_and_retrieve_entity_values_in_type_specific_columns(): void
    {
        [$user, $token] = $this->authenticatedUser();

        CustomField::query()->create([
            'organization_id' => $user->organization_id,
            'entity_type' => 'contacts',
            'name' => 'Annual Revenue',
            'slug' => 'annual_revenue',
            'type' => 'number',
            'is_required' => true,
            'sort_order' => 2,
        ]);
        CustomField::query()->create([
            'organization_id' => $user->organization_id,
            'entity_type' => 'contacts',
            'name' => 'Preferred Channels',
            'slug' => 'preferred_channels',
            'type' => 'multi_select',
            'options' => ['choices' => ['email', 'phone']],
            'sort_order' => 1,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/contacts/100/custom-field-values', [
                'values' => [
                    'annual_revenue' => 12345.67,
                    'preferred_channels' => ['email', 'phone'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.custom_field.slug', 'preferred_channels')
            ->assertJsonPath('data.0.value', ['email', 'phone'])
            ->assertJsonPath('data.1.custom_field.slug', 'annual_revenue')
            ->assertJsonPath('data.1.value', 12345.67);

        $this->assertDatabaseHas('custom_field_values', [
            'organization_id' => $user->organization_id,
            'entity_type' => 'contacts',
            'entity_id' => 100,
            'value_number' => 12345.67,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/contacts/100/custom-field-values')
            ->assertOk()
            ->assertJsonPath('data.0.custom_field.slug', 'preferred_channels')
            ->assertJsonPath('data.0.value', ['email', 'phone']);
    }

    public function test_value_validation_uses_custom_field_rules_and_options(): void
    {
        [$user, $token] = $this->authenticatedUser();

        CustomField::query()->create([
            'organization_id' => $user->organization_id,
            'entity_type' => 'deals',
            'name' => 'Stage',
            'slug' => 'stage',
            'type' => 'select',
            'options' => ['choices' => ['new', 'won']],
            'is_required' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/deals/55/custom-field-values', [
                'values' => [
                    'stage' => 'lost',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.stage');
    }

    public function test_custom_field_routes_require_custom_field_rights(): void
    {
        [$user, $token] = $this->authenticatedUserWithRights([]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/custom-fields')
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/custom-fields', [
                'entity_type' => 'contacts',
                'name' => 'Lifecycle Stage',
                'slug' => 'lifecycle_stage',
                'type' => 'text',
            ])
            ->assertForbidden();

        CustomField::query()->create([
            'organization_id' => $user->organization_id,
            'entity_type' => 'contacts',
            'name' => 'Lifecycle Stage',
            'slug' => 'lifecycle_stage',
            'type' => 'text',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/contacts/100/custom-field-values')
            ->assertForbidden();
    }

    private function authenticatedUser(): array
    {
        return $this->authenticatedUserWithRights(['custom-fields.view', 'custom-fields.manage']);
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
            'organization_id' => $user->organization_id,
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->create([
                'name' => $rightName,
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
