<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->timestamp('publish_at')->nullable()->after('description');
            $table->timestamp('expires_at')->nullable()->after('publish_at');
            $table->unsignedInteger('priority')->default(0)->after('expires_at');
            $table->string('status', 20)->default('draft')->after('priority');
            $table->string('audience_segment', 30)->default('all_users')->after('status');
            $table->index(['organization_id', 'status', 'publish_at', 'expires_at'], 'articles_publication_index');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex('articles_publication_index');
            $table->dropColumn(['publish_at', 'expires_at', 'priority', 'status', 'audience_segment']);
        });
    }
};
