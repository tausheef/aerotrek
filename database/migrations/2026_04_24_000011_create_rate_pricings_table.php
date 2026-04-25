<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('carrier');
            $table->string('zone');
            $table->string('shipment_type');
            $table->decimal('weight', 8, 2);
            $table->decimal('price', 10, 2);
            $table->boolean('is_per_kg')->default(false);
            $table->string('tier')->nullable();
            $table->string('route')->nullable();
            $table->timestamps();

            $table->index(['carrier', 'zone', 'shipment_type', 'weight']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_pricings');
    }
};
