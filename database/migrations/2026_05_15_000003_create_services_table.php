<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services') || Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('billing_interval', ['monthly', 'yearly']);
            $table->integer('duration_days')->nullable();
            $table->integer('trial_days')->default(0);
            $table->integer('max_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('billing_interval');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::dropIfExists('services');
    }
};
