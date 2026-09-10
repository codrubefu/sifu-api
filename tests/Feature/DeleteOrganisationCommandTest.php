<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteOrganisationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_organization_and_owned_data(): void
    {
        Artisan::call('create:organisation', [
            '--organization' => 'Delete Me',
            '--description' => 'Temporary organization',
            '--email' => 'admin@delete.test',
            '--first-name' => 'Delete',
            '--last-name' => 'Admin',
            '--password' => 'password123',
        ]);

        $organization = Organization::query()->where('name', 'Delete Me')->firstOrFail();
        $user = User::query()->where('organization_id', $organization->id)->firstOrFail();
        $group = Group::query()->where('organization_id', $organization->id)->firstOrFail();
        $location = Location::query()->create([
            'name' => 'Delete Location',
            'description' => 'Temporary location',
            'organization_id' => $organization->id,
        ]);
        $user->locations()->attach($location);
        $rightsCount = Right::query()->count();

        $exitCode = Artisan::call('delete:organisation', [
            'id' => $organization->id,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
        $this->assertDatabaseMissing('group_user', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('group_right', ['group_id' => $group->id]);
        $this->assertDatabaseMissing('location_user', ['user_id' => $user->id]);
        $this->assertSame($rightsCount, Right::query()->count());
        $this->assertSame(0, DB::table('audit_logs')->where('changed_by', $user->id)->count());
    }
}
