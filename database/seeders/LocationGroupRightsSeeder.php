<?php

namespace Database\Seeders;

use App\Users\Models\Group;
use App\Users\Models\Right;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LocationGroupRightsSeeder extends Seeder
{
    public function run(): void
    {
        $rights = self::rights()->mapWithKeys(fn (array $right) => [
            $right['name'] => Right::query()->updateOrCreate(
                ['name' => $right['name']],
                ['label' => $right['label'], 'description' => $right['description']],
            ),
        ]);

        Group::query()
            ->where('name', 'admin')
            ->each(fn (Group $group) => $group->rights()->syncWithoutDetaching($rights->pluck('id')));

        Group::query()
            ->where('name', 'manager')
            ->each(fn (Group $group) => $group->rights()->syncWithoutDetaching([
                $rights->get('location_groups.view')->id,
            ]));
    }

    public static function rights(): Collection
    {
        return collect([
            ['name' => 'location_groups.view', 'label' => 'View location groups', 'description' => 'Read location groups and their locations.'],
            ['name' => 'location_groups.manage', 'label' => 'Manage location groups', 'description' => 'Create, update, and delete location groups.'],
        ]);
    }
}
