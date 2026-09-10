<?php

namespace Database\Seeders;

use App\Users\Models\Organization;
use App\Users\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class UsersOnlySeeder extends Seeder
{
    public const DEFAULT_COUNT = 20;

    private static ?int $organizationId = null;

    public static function forOrganization(int $organizationId): void
    {
        self::$organizationId = $organizationId;
    }

    /**
     * Seed users for a single existing organization.
     */
    public function run(): void
    {
        $organizationId = self::$organizationId ?? $this->organizationIdFromEnvironment();

        if ($organizationId === null) {
            throw new InvalidArgumentException(
                'Organization ID is required. Run `php artisan seed:users {organization_id}` or set USERS_SEED_ORGANIZATION_ID before using this seeder directly.'
            );
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            throw new InvalidArgumentException("Organization [{$organizationId}] was not found.");
        }

        User::factory()
            ->count(self::DEFAULT_COUNT)
            ->create([
                'organization_id' => $organization->id,
            ]);
    }

    private function organizationIdFromEnvironment(): ?int
    {
        $organizationId = env('USERS_SEED_ORGANIZATION_ID');

        if ($organizationId === null || $organizationId === '') {
            return null;
        }

        return (int) $organizationId;
    }
}
