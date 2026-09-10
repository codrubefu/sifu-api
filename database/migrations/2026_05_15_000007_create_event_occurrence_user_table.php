<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_occurrence_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['registered', 'attended', 'cancelled', 'no_show'])->default('registered');
            $table->timestamp('registered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_occurrence_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrence_user');
    }
};
