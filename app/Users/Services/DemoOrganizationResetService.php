<?php

namespace App\Users\Services;

use App\CustomFields\Services\CustomFieldDefinitionService;
use App\Users\Models\Organization;
use App\Users\Models\User;
use Database\Seeders\DemoOrganizationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoOrganizationResetService
{
    public function reset(): Organization
    {
        return Cache::lock('demo-organization-reset', 300)->block(10, fn () => Model::withoutEvents(
            fn () => DB::transaction(function (): Organization {
                $organizations = Organization::query()->where('is_demo', true)->lockForUpdate()->get();
                if ($organizations->count() > 1) {
                    throw new RuntimeException('Multiple demo organizations found; no data was changed.');
                }
                $organization = $organizations->first();
                if ($organization === null) {
                    if (Organization::query()->where('slug', 'demo')->orWhere('name', 'Club Demo')->exists()) {
                        throw new RuntimeException('Demo name or slug belongs to an unflagged organization; no data was changed.');
                    }
                    $organization = new Organization;
                    $organization->forceFill(['name' => 'Club Demo', 'slug' => 'demo', 'is_demo' => true]);
                }
                $planId = DB::table('organization_plans')->where('code', 'pro')->value('id');
                if ($planId === null) {
                    throw new RuntimeException('The pro organization plan is missing.');
                }
                $organization->plan_id = $planId;
                $organization->saveQuietly();

                $admin = User::withoutGlobalScopes()->where('organization_id', $organization->id)
                    ->where('id', $organization->demo_admin_user_id)->first();
                // Allows bootstrapping a flagged organization without a saved identity.
                $admin ??= User::withoutGlobalScopes()->where('organization_id', $organization->id)
                    ->where('email', 'demo@club.com')->first();
                $this->wipe($organization, $admin);
                app(DemoOrganizationSeeder::class)->run($organization, $admin);

                return $organization->refresh();
            })
        ));
    }

    private function wipe(Organization $organization, ?User $admin): void
    {
        // Never accept a caller-supplied tenant ID as deletion authorization.
        if (! $organization->is_demo) {
            throw new RuntimeException('Only a flagged demo organization can be reset.');
        }
        $id = $organization->id;
        $users = DB::table('users')->where('organization_id', $id)->pluck('id');
        $services = DB::table('services')->where('organization_id', $id)->pluck('id');
        $deliveries = DB::table('notification_deliveries')->whereIn('user_id', $users)->pluck('id');
        DB::table('notification_attempts')->whereIn('notification_delivery_id', $deliveries)->delete();
        DB::table('notification_deliveries')->whereIn('user_id', $users)->delete();
        DB::table('sms_messages')->whereIn('user_id', $users)->orWhereIn('service_id', $services)->delete();

        $entityTypes = DB::table('custom_fields')->where('organization_id', $id)->distinct()->pluck('entity_type');
        // Cache invalidation is repeated after commit so readers cannot retain old IDs.
        foreach ($entityTypes as $type) {
            app(CustomFieldDefinitionService::class)->clearCache($id, $type);
        }
        DB::afterCommit(function () use ($id, $entityTypes): void {
            foreach ($entityTypes as $type) {
                app(CustomFieldDefinitionService::class)->clearCache($id, $type);
            }
        });

        // Capture private files before deleting their metadata. Delete only after commit.
        foreach (['user_documents', 'report_exports', 'gdpr_exports'] as $table) {
            $files = DB::table($table)->where('organization_id', $id)->get($table === 'report_exports' ? ['path'] : ['disk', 'path']);
            DB::afterCommit(function () use ($files, $table): void {
                foreach ($files as $file) {
                    if ($file->path) {
                        Storage::disk($table === 'report_exports' ? 'local' : $file->disk)->delete($file->path);
                    }
                }
            });
        }

        DB::table('service_user')->whereIn('service_id', $services)->delete();
        // Dependent pivots/attempts use database cascades; raw deletes include soft-deleted rows.
        foreach ([
            'campaigns', 'articles', 'segments', 'payments', 'event_occurrences', 'events',
            'event_categories', 'services', 'custom_field_values', 'custom_fields', 'audit_logs',
            'user_documents', 'user_grades', 'grades', 'report_exports', 'gdpr_exports',
            'gdpr_requests', 'consent_records', 'smtp_settings', 'email_templates', 'organization_limit_overrides',
            'organization_event_limit_blocks',
        ] as $table) {
            DB::table($table)->where('organization_id', $id)->delete();
        }
        foreach (['notification_preferences', 'push_devices', 'password_setup_tokens', 'sessions', 'group_user', 'location_user'] as $table) {
            DB::table($table)->whereIn('user_id', $users)->delete();
        }
        DB::table('users')->where('organization_id', $id)
            ->when($admin !== null, fn ($query) => $query->where('id', '!=', $admin->id))->delete();
        DB::table('locations')->where('organization_id', $id)->delete();
        DB::table('location_groups')->where('organization_id', $id)->delete();
    }
}
