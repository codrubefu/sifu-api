<?php

namespace App\Console\Commands;

use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Database\Seeders\CustomFieldRightsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateOrganizationAdmin extends Command
{
    protected $signature = 'create:organisation
        {--organization= : Organization name}
        {--slug= : Organization slug}
        {--url= : Organization frontend URL, used to build links sent by e-mail}
        {--description= : Organization description}
        {--email= : Administrator email}
        {--first-name= : Administrator first name}
        {--last-name= : Administrator last name}
        {--password= : Administrator password}';

    protected $description = 'Create an organization with an administrator user.';

    public function handle(): int
    {
        $organizationName = $this->option('organization') ?: $this->ask('Organization name');

        $data = [
            'organization' => $organizationName,
            'slug' => $this->option('slug') ?: $this->ask('Organization slug', Str::slug($organizationName)),
            'url' => $this->option('url'),
            'description' => $this->option('description') ?: $this->ask('Organization description'),
            'email' => $this->option('email') ?: $this->ask('Administrator email'),
            'first_name' => $this->option('first-name') ?: $this->ask('Administrator first name'),
            'last_name' => $this->option('last-name') ?: $this->ask('Administrator last name'),
            'password' => $this->option('password') ?: $this->secret('Administrator password'),
        ];

        $validator = Validator::make($data, [
            'organization' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('organizations', 'slug')],
            'url' => ['nullable', 'string', 'max:255', 'url'],
            'description' => ['nullable', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        [$organization, $user, $adminGroup, $userGroup] = DB::transaction(function () use ($data): array {
            $organization = Organization::query()->create([
                'name' => $data['organization'],
                'slug' => $data['slug'],
                'url' => $data['url'],
                'description' => $data['description'],
            ]);

            $rights = $this->rights()->mapWithKeys(fn (array $right) => [
                $right['name'] => Right::query()->firstOrCreate(
                    ['name' => $right['name']],
                    [
                        'label' => $right['label'],
                        'description' => $right['description'],
                    ],
                ),
            ]);

            $adminGroup = Group::query()->create([
                'name' => $this->uniqueAdminGroupName($organization),
                'label' => 'Administrator',
                'description' => 'Full application access.',
                'organization_id' => $organization->id,
            ]);
            $adminGroup->rights()->sync($rights->pluck('id'));

            $userGroup = Group::query()->create([
                'name' => $this->uniqueUserGroupName($organization),
                'label' => 'User',
                'description' => 'Basic authenticated access.',
                'organization_id' => $organization->id,
            ]);
            $userGroup->rights()->sync([$rights->get('profile.view')->id]);

            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'active' => true,
                'organization_id' => $organization->id,
            ]);
            $user->groups()->sync([$adminGroup->id]);

            return [$organization, $user, $adminGroup, $userGroup];
        });

        $this->info('Organization administrator created.');
        $this->table(
            ['Organization ID', 'Organization', 'User ID', 'Email', 'Admin Group', 'User Group'],
            [[$organization->id, "{$organization->name} ({$organization->slug})", $user->id, $user->email, $adminGroup->name, $userGroup->name]],
        );

        return self::SUCCESS;
    }

    private function uniqueAdminGroupName(Organization $organization): string
    {
        $baseName = Str::slug($organization->name).'-admin';

        return $this->uniqueGroupName($baseName);
    }

    private function uniqueUserGroupName(Organization $organization): string
    {
        $baseName = Str::slug($organization->name).'-user';

        return $this->uniqueGroupName($baseName);
    }

    private function uniqueGroupName(string $baseName): string
    {
        $name = $baseName;
        $suffix = 1;

        while (Group::query()->where('name', $name)->exists()) {
            $name = "{$baseName}-{$suffix}";
            $suffix++;
        }

        return $name;
    }

    private function rights(): \Illuminate\Support\Collection
    {
        return collect([
            ['name' => 'profile.view', 'label' => 'View own profile', 'description' => 'Access the authenticated user profile.'],
            ['name' => 'users.view', 'label' => 'View users', 'description' => 'Read user records.'],
            ['name' => 'users.manage', 'label' => 'Manage users', 'description' => 'Create, update, and deactivate users.'],
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
            ['name' => 'articles.view', 'label' => 'View articles', 'description' => 'Read articles.'],
            ['name' => 'articles.create', 'label' => 'Create articles', 'description' => 'Create articles.'],
            ['name' => 'articles.update', 'label' => 'Update articles', 'description' => 'Update articles.'],
            ['name' => 'articles.delete', 'label' => 'Delete articles', 'description' => 'Delete articles.'],
            ['name' => 'articles.manage', 'label' => 'Manage articles', 'description' => 'Manage all article actions.'],
            ['name' => 'events.view', 'label' => 'View events', 'description' => 'Read events and event occurrences.'],
            ['name' => 'events.manage', 'label' => 'Manage events', 'description' => 'Create, update, and delete events.'],
            ['name' => 'event_participants.view', 'label' => 'View event participants', 'description' => 'Read event occurrence participants.'],
            ['name' => 'event_participants.manage', 'label' => 'Manage event participants', 'description' => 'Add and remove event occurrence participants.'],
            ['name' => 'payments.view', 'label' => 'View payments', 'description' => 'Read payment records.'],
            ['name' => 'payments.create', 'label' => 'Create payments', 'description' => 'Register payments.'],
            ['name' => 'payments.update', 'label' => 'Update payments', 'description' => 'Update payment payable model links.'],
            ['name' => 'payments.manage', 'label' => 'Manage payments', 'description' => 'Manage all payment actions.'],
            ['name' => 'dashboard.view', 'label' => 'View dashboard', 'description' => 'Read tenant dashboard aggregates.'],
            ['name' => 'reports.view', 'label' => 'View financial reports', 'description' => 'Read tenant financial aggregates.'],
            ['name' => 'reports.export', 'label' => 'Export financial reports', 'description' => 'Generate and download financial exports.'],
            ['name' => 'segments.view', 'label' => 'View segments', 'description' => 'Read and evaluate saved member segments.'],
            ['name' => 'segments.manage', 'label' => 'Manage segments', 'description' => 'Create, update, and delete saved member segments.'],
        ])->merge(CustomFieldRightsSeeder::rights());
    }
}
