<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atk_counters', function (Blueprint $table) {
            $table->string('date', 8)->primary(); // YYYYMMDD
            $table->unsignedBigInteger('seq')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atk_counters');
    }
};
