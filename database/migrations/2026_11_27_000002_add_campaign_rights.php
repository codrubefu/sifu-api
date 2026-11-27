<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security fix (F-03): campaign routes previously required only authentication, so any member could
 * read recipient contact data and send mass messages. They now require campaigns.view / campaigns.manage.
 * To keep existing staff access, both rights are granted to every group that already holds
 * users.manage or reports.manage (the rights the UI already used to show the campaigns screen).
 */
return new class extends Migration
{
    private const RIGHTS = [
        'campaigns.view' => ['label' => 'View campaigns', 'description' => 'Read campaigns, recipient previews, and delivery statistics.'],
        'campaigns.manage' => ['label' => 'Manage campaigns', 'description' => 'Create, edit, schedule, and cancel mass e-mail/SMS/push campaigns.'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $rightIds = [];

            foreach (self::RIGHTS as $name => $attributes) {
                $id = DB::table('rights')->where('name', $name)->value('id');
                $rightIds[] = $id ?? DB::table('rights')->insertGetId($attributes + [
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $groupIds = DB::table('group_right')
                ->join('rights', 'rights.id', '=', 'group_right.right_id')
                ->whereIn('rights.name', ['users.manage', 'reports.manage'])
                ->distinct()
                ->pluck('group_right.group_id');

            foreach ($groupIds as $groupId) {
                foreach ($rightIds as $rightId) {
                    DB::table('group_right')->insertOrIgnore([
                        'group_id' => $groupId,
                        'right_id' => $rightId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        $rightIds = DB::table('rights')->whereIn('name', array_keys(self::RIGHTS))->pluck('id');

        DB::table('group_right')->whereIn('right_id', $rightIds)->delete();
        DB::table('rights')->whereIn('id', $rightIds)->delete();
    }
};
