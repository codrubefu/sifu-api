<?php

namespace App\Console\Commands;

use Database\Seeders\UsersOnlySeeder;
use Illuminate\Console\Command;

class SeedUsers extends Command
{
    protected $signature = 'seed:users {organization_id : Existing organization ID that will own the generated users}';

    protected $description = 'Seed 20 users for an existing organization.';

    public function handle(): int
    {
        $organizationId = (int) $this->argument('organization_id');

        if ($organizationId < 1) {
            $this->error('The organization_id argument must be a positive integer.');

            return self::FAILURE;
        }

        UsersOnlySeeder::forOrganization($organizationId);
        $this->call('db:seed', ['--class' => UsersOnlySeeder::class]);

        $this->info("Seeded 20 users for organization [{$organizationId}].");

        return self::SUCCESS;
    }
}
