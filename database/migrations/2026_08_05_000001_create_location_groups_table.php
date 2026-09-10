<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->index('organization_id');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('location_group_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('location_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_group_id');
        });

        Schema::dropIfExists('location_groups');
    }
};
