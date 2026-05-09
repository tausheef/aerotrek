<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiprocket_rates', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 5);
            $table->decimal('weight', 7, 2);
            $table->string('service', 80);
            $table->unsignedInteger('rate');

            $table->index(['country_code', 'weight']);
            $table->index('service');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiprocket_rates');
    }
};
