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
        $rights = collect([
            ['name' => 'profile.view', 'label' => 'View own profile', 'description' => 'Access the authenticated user profile.'],
            ['name' => 'users.view', 'label' => 'View users', 'description' => 'Read user records.'],
            ['name' => 'users.manage', 'label' => 'Manage users', 'description' => 'Create, update, and deactivate users.'],
            ['name' => 'grades.view', 'label' => 'View grades', 'description' => 'Read organization grades and user grade history.'],
            ['name' => 'grades.manage', 'label' => 'Manage grades', 'description' => 'Create grades and manage user grade history.'],
            ['name' => 'user-documents.view', 'label' => 'View user documents', 'description' => 'Read and securely download member documents.'],
            ['name' => 'user-documents.upload', 'label' => 'Upload user documents', 'description' => 'Upload and replace member documents.'],
            ['name' => 'user-documents.delete', 'label' => 'Delete user documents', 'description' => 'Delete member documents from private storage.'],
            ['name' => 'groups.view', 'label' => 'View groups', 'description' => 'Read user groups and their rights.'],
            ['name' => 'groups.manage', 'label' => 'Manage groups', 'description' => 'Create, update, and delete user groups.'],
            ['name' => 'rights.view', 'label' => 'View rights', 'description' => 'Read available application rights.'],
            ['name' => 'rights.manage', 'label' => 'Manage rights', 'description' => 'Create, update, and delete application rights.'],
            ['name' => 'locations.view', 'label' => 'View locations', 'description' => 'Read locations and assigned users.'],
            ['name' => 'locations.manage', 'label' => 'Manage locations', 'description' => 'Create, update, and delete locations.'],
            ['name' => 'services.view', 'label' => 'View services', 'description' => 'Read services.'],
            ['name' => 'services.create', 'label' => 'Create services', 'description' => 'Create services.'],
            ['name' => 'services.update', 'label' => 'Update services', 'description' => 'Update services and toggle active status.'],
            ['name' => 'services.delete', 'label' => 'Delete services', 'description' => 'Delete services.'],
            ['name' => 'services.restore', 'label' => 'Restore services', 'description' => 'Restore deleted services.'],
            ['name' => 'services.manage', 'label' => 'Manage services', 'description' => 'Manage all service actions.'],
            ['name' => 'sms.view', 'label' => 'View SMS messages', 'description' => 'Read sent, pending, and failed SMS messages.'],
            ['name' => 'articles.view', 'label' => 'View articles', 'description' => 'Read articles.'],
            ['name' => 'articles.create', 'label' => 'Create articles', 'description' => 'Create articles.'],
            ['name' => 'articles.update', 'label' => 'Update articles', 'description' => 'Update articles.'],
            ['name' => 'articles.delete', 'label' => 'Delete articles', 'description' => 'Delete articles.'],
            ['name' => 'articles.manage', 'label' => 'Manage articles', 'description' => 'Manage all article actions.'],
            ['name' => 'events.view', 'label' => 'View events', 'description' => 'Read events and event occurrences.'],
            ['name' => 'events.manage', 'label' => 'Manage events', 'description' => 'Create, update, and delete events.'],
            ['name' => 'event_participants.view', 'label' => 'View event participants', 'description' => 'Read event occurrence participants.'],
            ['name' => 'event_participants.manage', 'label' => 'Manage event participants', 'description' => 'Add and remove event occurrence participants.'],
            ['name' => 'checkins.manage', 'label' => 'Manage check-ins', 'description' => 'Search members and confirm reception check-ins.'],
            ['name' => 'checkins.override', 'label' => 'Override check-ins', 'description' => 'Allow explicit reception check-in overrides for invalid access.'],
            ['name' => 'payments.view', 'label' => 'View payments', 'description' => 'Read payment records.'],
            ['name' => 'payments.create', 'label' => 'Create payments', 'description' => 'Register payments.'],
            ['name' => 'payments.update', 'label' => 'Update payments', 'description' => 'Update payment payable model links.'],
            ['name' => 'payments.manage', 'label' => 'Manage payments', 'description' => 'Manage all payment actions.'],
            ['name' => 'dashboard.view', 'label' => 'View dashboard', 'description' => 'Read tenant dashboard aggregates.'],
            ['name' => 'reports.view', 'label' => 'View financial reports', 'description' => 'Read tenant financial aggregates.'],
            ['name' => 'reports.export', 'label' => 'Export financial reports', 'description' => 'Generate and download financial exports.'],
            ['name' => 'segments.view', 'label' => 'View segments', 'description' => 'Read and evaluate saved member segments.'],
            ['name' => 'segments.manage', 'label' => 'Manage segments', 'description' => 'Create, update, and delete saved member segments.'],
            ['name' => 'gdpr.export', 'label' => 'Export personal data', 'description' => 'Access and export personal data for tenant users.'],
            ['name' => 'gdpr.process', 'label' => 'Process GDPR requests', 'description' => 'Rectify data and process erasure requests.'],
            ['name' => 'smtp_settings.view', 'label' => 'View SMTP settings', 'description' => 'Read the organization outgoing mail (SMTP) settings.'],
            ['name' => 'smtp_settings.manage', 'label' => 'Manage SMTP settings', 'description' => 'Create, update, and delete the organization outgoing mail (SMTP) settings.'],
        ])
            ->merge(LocationGroupRightsSeeder::rights())
            ->merge(CustomFieldRightsSeeder::rights())
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
