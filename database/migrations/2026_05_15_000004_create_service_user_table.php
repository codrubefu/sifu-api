<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_user') || Schema::hasTable('subscription_user')) {
            return;
        }

        Schema::create('service_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->nullable();
            $table->string('bill_number')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'user_id']);
            $table->index('user_id');
            $table->index('invoice_number');
            $table->index('bill_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_user')) {
            return;
        }

        Schema::dropIfExists('service_user');
    }
};
