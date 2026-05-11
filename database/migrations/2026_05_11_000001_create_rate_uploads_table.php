<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename');                 // path in storage
            $table->string('original_name');            // original xlsx filename
            $table->enum('status', ['pending', 'processing', 'active', 'failed', 'superseded'])
                  ->default('pending');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->char('uploaded_by', 36)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('activated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_uploads');
    }
};
