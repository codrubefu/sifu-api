<?php

namespace Database\Seeders;

use App\Users\Models\Group;
use App\Users\Models\Right;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CustomFieldRightsSeeder extends Seeder
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
                $rights->get('custom-fields.view')->id,
            ]));
    }

    public static function rights(): Collection
    {
        return collect([
            ['name' => 'custom-fields.view', 'label' => 'View custom fields', 'description' => 'Read custom field definitions and values.'],
            ['name' => 'custom-fields.manage', 'label' => 'Manage custom fields', 'description' => 'Create, update, delete, and save custom field values.'],
        ]);
    }
}
