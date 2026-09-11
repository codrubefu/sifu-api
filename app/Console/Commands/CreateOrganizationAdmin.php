<?php

namespace App\Console\Commands;

use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateOrganizationAdmin extends Command
{
    protected $signature = 'create:organisation
        {--organization= : Organization name}
        {--plan=start : Organization subscription plan code}
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
            'plan' => $this->option('plan'),
            'slug' => $this->option('slug') ?: $this->ask('Organization slug', Str::slug($organizationName)),
            'url' => $this->option('url'),
            'description' => $this->option('description') ?: $this->ask('Organization description'),
            'email' => $this->option('email') ?: $this->ask('Administrator email'),
            'first_name' => $this->option('first-name') ?: $this->ask('Administrator first name'),
            'last_name' => $this->option('last-name') ?: $this->ask('Administrator last name'),
            'password' => $this->option('password') ?: $this->secret('Administrator password'),
        ];

        $validator = Validator::make($data, [
            'plan' => ['required', 'string', 'exists:organization_plans,code'],
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

            $organization->plan_id = DB::table('organization_plans')->where('code', $data['plan'])->value('id');
            $organization->save();

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
        return \Database\Seeders\ApplicationRights::definitions();
    }
}
