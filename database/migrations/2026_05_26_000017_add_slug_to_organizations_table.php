<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        DB::table('organizations')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->chunkById(100, function ($organizations): void {
                foreach ($organizations as $organization) {
                    DB::table('organizations')
                        ->where('id', $organization->id)
                        ->update(['slug' => $this->uniqueSlug($organization->name, $organization->id)]);
                }
            });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug', 'organizations_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique('organizations_slug_unique');
            $table->dropColumn('slug');
        });
    }

    private function uniqueSlug(string $name, int $organizationId): string
    {
        $baseSlug = Str::slug($name) ?: "organization-{$organizationId}";
        $slug = $baseSlug;
        $suffix = 1;

        while (
            DB::table('organizations')
                ->where('slug', $slug)
                ->where('id', '!=', $organizationId)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
};
