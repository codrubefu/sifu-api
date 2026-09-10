<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('entity_type');
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'entity_type']);
            $table->unique(['organization_id', 'entity_type', 'slug']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->dateTime('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('custom_field_id');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index(['organization_id', 'entity_type', 'entity_id']);
            $table->index(['custom_field_id', 'value_text']);
            $table->index(['custom_field_id', 'value_number']);
            $table->index(['custom_field_id', 'value_date']);
            $table->unique(['custom_field_id', 'entity_type', 'entity_id'], 'custom_field_values_unique_entity_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
