<?php

namespace Database\Seeders;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rights = ApplicationRights::definitions()
            ->mapWithKeys(fn (array $right) => [
                $right['name'] => Right::query()->updateOrCreate(
                    ['name' => $right['name']],
                    ['label' => $right['label'], 'description' => $right['description']],
                ),
            ]);

        $admin = Group::query()->updateOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrator', 'description' => 'Full application access.'],
        );
        $admin->rights()->sync($rights->pluck('id'));

        $manager = Group::query()->updateOrCreate(
            ['name' => 'manager'],
            ['label' => 'Manager', 'description' => 'Can view users, groups, and rights.'],
        );
        $manager->rights()->sync($rights->only([
            'profile.view',
            'users.view',
            'grades.view',
            'user-documents.view',
            'groups.view',
            'rights.view',
            'location_groups.view',
            'locations.view',
            'services.view',
            'sms.view',
            'articles.view',
            'events.view',
            'event_participants.view',
            'payments.view',
            'dashboard.view',
            'reports.view',
            'segments.view',
            'custom-fields.view',
            'smtp_settings.view',
            'organization_subscription.view',
        ])->pluck('id'));

        $staff = Group::query()->updateOrCreate(
            ['name' => 'staff'],
            ['label' => 'Staff', 'description' => 'Basic authenticated access.'],
        );
        $staff->rights()->sync($rights->only(['profile.view'])->pluck('id'));

        User::factory(10)->create()->each(fn (User $user) => $user->groups()->sync([$staff->id]));

     

        $this->call(LocationSeeder::class);
    }
}
