<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_document_id')->nullable()->constrained('user_documents')->nullOnDelete();
            $table->string('category', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('disk', 64)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('status', 32)->default('active');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'category']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
