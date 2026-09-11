<?php

namespace Database\Seeders;

use App\Articles\Models\Article;
use App\CustomFields\Models\CustomField;
use App\CustomFields\Services\CustomFieldDefinitionService;
use App\CustomFields\Services\CustomFieldValueService;
use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Events\Services\EventOccurrenceGeneratorService;
use App\Payments\Models\Payment;
use App\Service\Models\Service;
use App\Service\Models\ServiceUser;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class DemoOrganizationSeeder extends Seeder
{
    public function run(?Organization $organization = null, ?User $admin = null): void
    {
        if ($organization === null || ! $organization->is_demo) {
            throw new InvalidArgumentException('Run demo:reset to seed the demo organization.');
        }
        $id = $organization->id;
        $rights = ApplicationRights::definitions()->map(fn ($right) => Right::firstOrCreate(
            ['name' => $right['name']], ['label' => $right['label'], 'description' => $right['description']],
        ));
        $groups = collect(['admin', 'manager', 'staff'])->mapWithKeys(function ($role) use ($id, $rights) {
            // Group names are globally unique in the current schema.
            $group = Group::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $id, 'name' => "demo-{$id}-{$role}"],
                ['label' => ucfirst($role), 'description' => "Demo {$role}"],
            );
            $group->rights()->sync($rights->filter(fn ($right) => $role === 'admin'
                || ($role === 'manager' && str_ends_with($right->name, '.view'))
                || $right->name === 'profile.view')->pluck('id'));

            return [$role => $group];
        });
        $admin ??= new User;
        $admin->forceFill([
            'organization_id' => $id, 'first_name' => 'Demo', 'last_name' => 'Administrator',
            'email' => 'demo@club.com', 'password' => Hash::make('password'), 'active' => true,
            'phone' => null, 'push_token' => null, 'parent_user_id' => null, 'user_code' => null,
            'notification_consents' => ['sms' => false, 'mail' => false, 'push' => false],
        ])->saveQuietly();
        $admin->groups()->sync([$groups['admin']->id]);
        $organization->forceFill(['demo_admin_user_id' => $admin->id])->saveQuietly();

        $locations = collect(['Centru', 'Nord'])->map(fn ($name) => Location::create([
            'organization_id' => $id, 'name' => "Demo {$name}", 'description' => "Strada Demo, {$name}",
        ]));
        $admin->locations()->sync($locations->pluck('id')->all());
        $members = collect(['Ana', 'Mihai', 'Elena', 'Andrei', 'Ioana', 'Vlad', 'Maria', 'Radu', 'Daria'])
            ->map(function ($name, $index) use ($id, $groups, $locations) {
                $user = User::create([
                    'organization_id' => $id, 'first_name' => $name, 'last_name' => 'Demo',
                    'email' => 'demo.member.'.($index + 1).'@example.test', 'password' => Hash::make('password'),
                    'active' => true, 'notification_consents' => ['sms' => false, 'mail' => false, 'push' => false],
                ]);
                $user->groups()->sync([$groups['staff']->id]);
                $user->locations()->sync([$locations[$index % 2]->id]);

                return $user;
            });
        $services = collect([
            ['Abonament lunar', 'membership', 200, 30],
            ['Abonament trimestrial', 'membership', 540, 90],
            ['Acces 10 antrenamente', 'access_pass', 180, 60],
            ['Ședință introductivă', 'access_pass', 0, 7],
        ])->map(fn ($data) => Service::create([
            'organization_id' => $id, 'name' => 'Demo '.$data[0], 'type' => $data[1],
            'price' => $data[2], 'currency' => 'RON', 'duration_days' => $data[3],
            'expiration_rule' => 'duration', 'max_accesses' => $data[1] === 'access_pass' ? 10 : null,
            'is_active' => true,
        ]));
        foreach ($members->take(6) as $index => $member) {
            $service = $services[$index % 4];
            $assignment = ServiceUser::create(['user_id' => $member->id, 'service_id' => $service->id, 'status' => 'pending']);
            $payment = (float) $service->price === 0 ? null : Payment::create([
                'organization_id' => $id, 'location_id' => $locations[$index % 2]->id,
                'first_name' => $member->first_name, 'last_name' => $member->last_name,
                'payment_type_id' => Payment::TYPE_CASH, 'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
                'model_id' => $assignment->id, 'amount' => $service->price, 'status' => Payment::STATUS_CONFIRMED,
                'admin_id' => $admin->id, 'paid_at' => now()->subDays(2), 'confirmed_at' => now()->subDays(2),
                'receipt_number' => "DEMO-{$id}-".($index + 1),
            ]);
            app(ServiceLifecycleService::class)->activate($assignment, $payment, now()->subDays(2), notify: false);
        }
        foreach ($members->slice(6) as $index => $member) {
            Payment::create([
                'organization_id' => $id, 'location_id' => $locations[$index % 2]->id,
                'first_name' => $member->first_name, 'last_name' => $member->last_name,
                'payment_type_id' => Payment::TYPE_BANK_TRANSFER, 'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
                'amount' => 150, 'status' => Payment::STATUS_CONFIRMED, 'admin_id' => $admin->id,
                'paid_at' => now()->subDays(20 + $index), 'confirmed_at' => now()->subDays(20 + $index),
                'receipt_number' => "DEMO-{$id}-".($index + 1),
            ]);
        }
        foreach (['Karate copii', 'Karate adulți', 'Pregătire examen'] as $index => $title) {
            $event = Event::create([
                'organization_id' => $id, 'title' => 'Demo '.$title, 'location_id' => $locations[$index % 2]->id,
                'instructor_id' => $admin->id, 'start_time' => '18:00:00', 'end_time' => '19:00:00',
                'recurrence_type' => 'weekly', 'recurrence_days' => [strtolower(now()->englishDayOfWeek)],
                'start_date' => now()->subWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
                'status' => 'active', 'max_participants' => 20,
            ]);
            app(EventOccurrenceGeneratorService::class)->generateForNewEvent($event);
            $occurrence = $event->occurrences()->orderBy('occurrence_date')->firstOrFail();
            foreach ($members->take(2) as $member) {
                $occurrence->participants()->attach($member->id, ['status' => 'attended', 'registered_at' => $occurrence->start_datetime]);
                AuditLog::query()->forceCreate([
                    'organization_id' => $id, 'subject_user_id' => $member->id,
                    'model_type' => EventOccurrence::class, 'model_id' => $occurrence->id,
                    'action' => AuditLog::CHECKIN_ACCEPTED, 'event_type' => AuditLog::CHECKIN_ACCEPTED,
                    'changed_by' => $admin->id, 'new_values' => ['event_occurrence_id' => $occurrence->id, 'location_id' => $locations[$index % 2]->id],
                    'created_at' => $occurrence->start_datetime,
                ]);
            }
        }
        foreach (['published', 'draft', 'expired'] as $index => $status) {
            Article::create([
                'organization_id' => $id, 'created_by' => $admin->id, 'title' => 'Demo '.['Bun venit la club', 'Seminar de weekend', 'Examenul de vară'][$index],
                'description' => 'Informații demonstrative despre activitățile clubului.', 'status' => $status,
                'audience_segment' => 'all_users', 'publish_at' => $status === 'draft' ? null : now()->subDays(10),
                'expires_at' => $status === 'expired' ? now()->subDay() : null,
            ]);
        }
        foreach ([['users', 'text', 'obiectiv'], ['users', 'number', 'experienta'], ['users', 'boolean', 'echipament'], ['events', 'text', 'nivel']] as [$entity, $type, $slug]) {
            CustomField::create(['organization_id' => $id, 'entity_type' => $entity, 'name' => 'Demo '.$slug, 'slug' => $slug, 'type' => $type]);
            app(CustomFieldDefinitionService::class)->clearCache($id, $entity);
        }
        foreach ($members->take(3) as $member) {
            app(CustomFieldValueService::class)->saveValues($id, 'users', $member->id, ['obiectiv' => 'Centura următoare', 'experienta' => 2, 'echipament' => true]);
        }
        foreach ([['Membri activi', ['active' => true]], ['Abonamente', ['service_type' => 'membership']], ['Locația Centru', ['location_id' => $locations[0]->id]]] as $index => [$name, $criteria]) {
            $segment = $this->insert('segments', ['organization_id' => $id, 'created_by' => $admin->id, 'name' => 'Demo '.$name, 'criteria' => json_encode($criteria)]);
            $campaign = $this->insert('campaigns', [
                'organization_id' => $id, 'created_by' => $admin->id, 'segment_id' => $segment,
                'name' => 'Demo '.$name, 'channel' => 'mail', 'subject' => 'Noutăți de la club',
                'content' => 'Mesaj demonstrativ, fără trimitere reală.', 'status' => 'sent', 'dispatched_at' => now()->subDay(),
            ]);
            $this->insert('notification_deliveries', [
                'user_id' => $members[$index]->id, 'campaign_id' => $campaign, 'event_type' => 'campaign',
                'event_key' => "demo:{$id}:{$index}", 'channel' => 'mail', 'template' => 'campaign',
                'payload' => json_encode(['subject' => 'Demo club', 'message' => 'Mesaj demonstrativ']),
                'status' => 'sent', 'sent_at' => now()->subDay(),
            ]);
            $this->insert('sms_messages', [
                'user_id' => $members[$index]->id, 'type' => 'demo', 'destination' => '0000000000',
                'message' => 'Demo: următorul antrenament începe la ora 18.', 'status' => 'sent', 'sent_at' => now()->subDay(),
            ]);
        }
    }

    private function insert(string $table, array $values): int
    {
        return DB::table($table)->insertGetId($values + ['created_at' => now(), 'updated_at' => now()]);
    }
}
