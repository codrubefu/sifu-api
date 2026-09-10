<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->unsignedTinyInteger('payment_type_id');
            $table->string('model_type')->default('service_user');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->dateTime('paid_at');
            $table->foreignId('admin_id')->constrained('users');
            $table->timestamps();

            $table->index('payment_type_id');
            $table->index('model_type');
            $table->index('model_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
