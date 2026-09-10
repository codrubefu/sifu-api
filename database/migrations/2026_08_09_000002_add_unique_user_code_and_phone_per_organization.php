<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['organization_id', 'user_code'], 'users_organization_user_code_unique');
            $table->unique(['organization_id', 'phone'], 'users_organization_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_organization_user_code_unique');
            $table->dropUnique('users_organization_phone_unique');
        });
    }
};
