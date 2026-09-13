<?php

namespace Database\Seeders;

use App\Users\Models\Right;
use Illuminate\Database\Seeder;

class EmailTemplateRightsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ApplicationRights::definitions()->whereIn('name', ['email_templates.view', 'email_templates.manage']) as $definition) {
            Right::query()->firstOrCreate(['name' => $definition['name']], $definition);
        }
    }
}
