<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->after('changed_by')->constrained('users')->nullOnDelete();
            $table->string('event_type', 64)->nullable()->after('action');
            $table->index(['organization_id', 'subject_user_id', 'created_at'], 'audit_activity_lookup');
            $table->index(['organization_id', 'event_type', 'created_at'], 'audit_event_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_activity_lookup');
            $table->dropIndex('audit_event_lookup');
            $table->dropConstrainedForeignId('subject_user_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('event_type');
        });
    }
};
