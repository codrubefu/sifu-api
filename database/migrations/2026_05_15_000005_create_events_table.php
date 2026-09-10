<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('recurrence_type', ['once', 'weekly', 'monthly']);
            $table->json('recurrence_days')->nullable();
            $table->unsignedTinyInteger('monthly_day')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('requires_active_service')->default(false);
            $table->foreignId('required_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedInteger('max_participants')->nullable();
            $table->enum('status', ['active', 'inactive', 'cancelled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'recurrence_type']);
            $table->index('start_date');
            $table->index('requires_active_service');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
