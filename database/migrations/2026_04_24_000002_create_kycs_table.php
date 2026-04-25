<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kycs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->index();
            $table->enum('account_type', ['individual', 'company']);
            $table->enum('document_type', ['aadhaar', 'pan', 'gst', 'company_pan']);
            $table->string('document_number');
            $table->string('document_image')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected', 'auto_verified'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->string('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_driver')->nullable();
            $table->json('verification_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kycs');
    }
};
