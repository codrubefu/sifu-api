<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security fix (F-02): the rights catalogue is platform-wide (no organization_id) and authorization
 * matches rights by name, so `rights.manage` let any tenant admin rename/create/delete rights for every
 * organization. The write API was removed; this revokes the now meaningless right from every group.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rightIds = DB::table('rights')->where('name', 'rights.manage')->pluck('id');

        if ($rightIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rightIds): void {
            DB::table('group_right')->whereIn('right_id', $rightIds)->delete();
            DB::table('rights')->whereIn('id', $rightIds)->delete();
        });
    }

    public function down(): void
    {
        // Intentionally not re-created or re-assigned: restoring tenant write access to the global
        // rights catalogue would reintroduce the cross-tenant privilege escalation.
    }
};
