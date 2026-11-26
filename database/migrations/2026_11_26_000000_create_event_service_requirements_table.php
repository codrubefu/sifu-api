<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the single `events.required_service_id` with service groups:
 * one OR-group per `group_index`, AND between groups. Existing single-service
 * requirements become group 0 with one service.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Read legacy requirements first: on SQLite, dropping a column rebuilds `events`, which would
        // cascade-delete rows already inserted into a table that references it.
        $legacy = Schema::hasColumn('events', 'required_service_id')
            ? DB::table('events')
                ->whereNotNull('required_service_id')
                ->whereIn('required_service_id', DB::table('services')->select('id'))
                ->orderBy('id')
                ->get(['id', 'required_service_id'])
            : collect();

        if (Schema::hasColumn('events', 'required_service_id')) {
            $this->dropRequiredServiceColumn();
        }

        Schema::create('event_service_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedSmallInteger('group_index');
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'service_id']);
            $table->index(['event_id', 'group_index']);
        });

        $now = now();
        $legacy->chunk(500)->each(fn ($events) => DB::table('event_service_requirements')->insert($events->map(fn (object $event): array => [
            'event_id' => $event->id,
            'group_index' => 0,
            'service_id' => $event->required_service_id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all()));
    }

    public function down(): void
    {
        // Lossy by nature: only the first service of group 0 can be represented by the old column.
        $firstServices = Schema::hasTable('event_service_requirements')
            ? DB::table('event_service_requirements')
                ->where('group_index', 0)
                ->orderBy('event_id')
                ->orderBy('id')
                ->get(['event_id', 'service_id'])
                ->unique('event_id')
            : collect();

        Schema::dropIfExists('event_service_requirements');

        if (! Schema::hasColumn('events', 'required_service_id')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->foreignId('required_service_id')->nullable()->after('requires_active_service')->constrained('services')->nullOnDelete();
            });
        }

        $firstServices->each(fn (object $row): int => DB::table('events')->where('id', $row->event_id)->update(['required_service_id' => $row->service_id]));
    }

    private function dropRequiredServiceColumn(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('events'))
            ->filter(fn (array $foreignKey): bool => $foreignKey['columns'] === ['required_service_id']);

        if ($foreignKeys->isNotEmpty()) {
            Schema::table('events', function (Blueprint $table) use ($foreignKeys): void {
                if (Schema::getConnection()->getDriverName() === 'sqlite') {
                    // SQLite cannot drop constraints by name; the table is rebuilt without it.
                    $table->dropForeign(['required_service_id']);

                    return;
                }

                // Look up the real name: databases migrated from `required_subscription_id` keep the old constraint name.
                foreach ($foreignKeys->pluck('name')->filter() as $name) {
                    $table->dropForeign($name);
                }
            });
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('required_service_id');
        });
    }
};
