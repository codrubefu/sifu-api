<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('web')->nullable();
            $table->string('cui')->nullable();
            $table->string('nr_reg_com')->nullable();
            $table->string('capital')->nullable();
            $table->string('cont')->nullable();
            $table->string('bank')->nullable();
            $table->string('receipt_code')->default('CH');
            $table->unsignedBigInteger('receipt_number')->default(0);
            $table->string('invoice_code')->default('INV');
            $table->unsignedBigInteger('invoice_number')->default(0);
            $table->string('bill_code')->default('BILL');
            $table->unsignedBigInteger('bill_number')->default(0);
            $table->timestamps();
        });

        foreach (['users', 'groups', 'rights', 'locations', 'articles', 'services', 'events', 'event_occurrences', 'personal_access_tokens'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
                $table->index('organization_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'groups', 'rights', 'locations', 'articles', 'services', 'events', 'event_occurrences', 'personal_access_tokens'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }

        Schema::dropIfExists('organizations');
    }
};
