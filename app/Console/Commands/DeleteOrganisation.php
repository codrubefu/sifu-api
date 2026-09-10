<?php

namespace App\Console\Commands;

use App\Articles\Models\Article;
use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\PersonalAccessToken;
use App\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteOrganisation extends Command
{
    protected $signature = 'delete:organisation
        {id : Organization ID}
        {--force : Delete without confirmation}';

    protected $description = 'Delete an organization and all records that belong to it.';

    public function handle(): int
    {
        $organization = Organization::query()->find($this->argument('id'));

        if (! $organization) {
            $this->error('Organization not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete organization [{$organization->id}] {$organization->name} and all its data?")) {
            $this->warn('Deletion cancelled.');

            return self::SUCCESS;
        }

        $summary = DB::transaction(fn (): array => $this->deleteOrganizationData($organization));

        $this->info('Organization deleted.');
        $this->table(
            ['Organization ID', 'Users', 'Groups', 'Locations', 'Articles', 'Services', 'Events', 'Occurrences', 'Tokens'],
            [[
                $organization->id,
                $summary['users'],
                $summary['groups'],
                $summary['locations'],
                $summary['articles'],
                $summary['services'],
                $summary['events'],
                $summary['event_occurrences'],
                $summary['personal_access_tokens'],
            ]],
        );

        return self::SUCCESS;
    }

    private function deleteOrganizationData(Organization $organization): array
    {
        $organizationId = $organization->id;
        $userIds = DB::table('users')->where('organization_id', $organizationId)->pluck('id');
        $groupIds = DB::table('groups')->where('organization_id', $organizationId)->pluck('id');
        $locationIds = DB::table('locations')->where('organization_id', $organizationId)->pluck('id');
        $articleIds = DB::table('articles')->where('organization_id', $organizationId)->pluck('id');
        $serviceIds = DB::table('services')->where('organization_id', $organizationId)->pluck('id');
        $eventIds = DB::table('events')->where('organization_id', $organizationId)->pluck('id');
        $eventOccurrenceIds = DB::table('event_occurrences')->where('organization_id', $organizationId)->pluck('id');
        $personalAccessTokenIds = DB::table('personal_access_tokens')->where('organization_id', $organizationId)->pluck('id');

        $summary = [
            'users' => $userIds->count(),
            'groups' => $groupIds->count(),
            'locations' => $locationIds->count(),
            'articles' => $articleIds->count(),
            'services' => $serviceIds->count(),
            'events' => $eventIds->count(),
            'event_occurrences' => $eventOccurrenceIds->count(),
            'personal_access_tokens' => $personalAccessTokenIds->count(),
        ];

        DB::table('event_occurrence_user')
            ->whereIn('event_occurrence_id', $eventOccurrenceIds)
            ->orWhereIn('user_id', $userIds)
            ->delete();
        DB::table('article_location')
            ->whereIn('article_id', $articleIds)
            ->orWhereIn('location_id', $locationIds)
            ->delete();
        DB::table('article_group')
            ->whereIn('article_id', $articleIds)
            ->orWhereIn('group_id', $groupIds)
            ->delete();
        DB::table('service_user')
            ->whereIn('service_id', $serviceIds)
            ->orWhereIn('user_id', $userIds)
            ->delete();
        DB::table('location_user')
            ->whereIn('location_id', $locationIds)
            ->orWhereIn('user_id', $userIds)
            ->delete();
        DB::table('group_user')
            ->whereIn('group_id', $groupIds)
            ->orWhereIn('user_id', $userIds)
            ->delete();
        DB::table('group_right')->whereIn('group_id', $groupIds)->delete();

        DB::table('audit_logs')
            ->whereIn('changed_by', $userIds)
            ->orWhere(fn ($query) => $this->auditLogScope($query, Organization::class, [$organizationId]))
            ->orWhere(fn ($query) => $this->auditLogScope($query, User::class, $userIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, Group::class, $groupIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, Location::class, $locationIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, Article::class, $articleIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, Service::class, $serviceIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, Event::class, $eventIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, EventOccurrence::class, $eventOccurrenceIds->all()))
            ->orWhere(fn ($query) => $this->auditLogScope($query, PersonalAccessToken::class, $personalAccessTokenIds->all()))
            ->delete();

        DB::table('personal_access_tokens')->whereIn('id', $personalAccessTokenIds)->delete();
        DB::table('event_occurrences')->whereIn('id', $eventOccurrenceIds)->delete();
        DB::table('events')->whereIn('id', $eventIds)->delete();
        DB::table('articles')->whereIn('id', $articleIds)->delete();
        DB::table('services')->whereIn('id', $serviceIds)->delete();
        DB::table('locations')->whereIn('id', $locationIds)->delete();
        DB::table('groups')->whereIn('id', $groupIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
        DB::table('organizations')->where('id', $organizationId)->delete();

        return $summary;
    }

    private function auditLogScope($query, string $modelType, array $modelIds): void
    {
        $query->where('model_type', $modelType)
            ->whereIn('model_id', $modelIds);
    }
}
