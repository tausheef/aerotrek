<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_zones', function (Blueprint $table) {
            $table->id();
            $table->string('carrier');
            $table->string('zone');
            $table->json('countries');
            $table->timestamps();

            $table->index(['carrier', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_zones');
    }
};
