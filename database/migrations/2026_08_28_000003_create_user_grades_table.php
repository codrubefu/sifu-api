<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->date('obtained_at');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id', 'obtained_at', 'id']);
            $table->index(['organization_id', 'grade_id', 'obtained_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_grades');
    }
};
