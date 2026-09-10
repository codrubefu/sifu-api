<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->enum('status', ['scheduled', 'cancelled', 'completed'])->default('scheduled');
            $table->timestamps();

            $table->unique(['event_id', 'occurrence_date']);
            $table->index(['status', 'occurrence_date']);
            $table->index('start_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
    }
};
