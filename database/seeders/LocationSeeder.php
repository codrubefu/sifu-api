<?php

namespace Database\Seeders;

use App\Users\Models\Location;
use App\Users\Models\User;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Seed application locations and assign users to them.
     */
    public function run(): void
    {
        $locations = collect([
            [
                'name' => 'Head Office',
                'description' => 'Primary office for administration and management.',
            ],
            [
                'name' => 'Warehouse',
                'description' => 'Inventory and fulfillment location.',
            ],
            [
                'name' => 'Remote',
                'description' => 'Remote users and field staff.',
            ],
        ])->map(fn (array $location) => Location::query()->updateOrCreate(
            ['name' => $location['name']],
            ['description' => $location['description']],
        ))->values();

        if ($locations->isEmpty()) {
            return;
        }

        User::query()
            ->orderBy('id')
            ->get()
            ->each(function (User $user, int $index) use ($locations): void {
                $location = $locations[$index % $locations->count()];

                $user->locations()->syncWithoutDetaching([$location->id]);
            });
    }
}
