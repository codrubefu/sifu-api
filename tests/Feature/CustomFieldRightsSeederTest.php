<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CustomFieldRightsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_field_rights_seeder_creates_rights_and_assigns_default_groups(): void
    {
        $admin = Group::query()->create([
            'name' => 'admin',
            'label' => 'Administrator',
        ]);
        $manager = Group::query()->create([
            'name' => 'manager',
            'label' => 'Manager',
        ]);

        $exitCode = Artisan::call('db:seed', [
            '--class' => 'CustomFieldRightsSeeder',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('rights', ['name' => 'custom-fields.view']);
        $this->assertDatabaseHas('rights', ['name' => 'custom-fields.manage']);
        $this->assertTrue($admin->rights()->where('name', 'custom-fields.manage')->exists());
        $this->assertTrue($manager->rights()->where('name', 'custom-fields.view')->exists());
        $this->assertFalse($manager->rights()->where('name', 'custom-fields.manage')->exists());
    }
}
