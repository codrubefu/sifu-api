<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->after('location_id')->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->after('instructor_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_id');
            $table->dropConstrainedForeignId('instructor_id');
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
