<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                  ->constrained('rate_uploads')
                  ->cascadeOnDelete();
            // UPS | FEDEX | DHL
            $table->string('carrier', 20);
            // document | non_document | pak | package | envelope | duty_free  (null = applies to all)
            $table->string('sub_type', 20)->nullable();
            // zone key matching RateCalculationService constants: zone7, usa, a, zone1, etc.
            $table->string('zone_key', 30);
            // weight slab key: '0.5', '1.0', '20+', '30+', etc.
            $table->string('weight_key', 15);
            // rate in INR (always all-inclusive — FSC already baked in)
            $table->unsignedInteger('rate');
            // true = rate is per-kg (multiply by chargeable weight)
            $table->boolean('is_per_kg')->default(false);

            $table->index(['upload_id', 'carrier', 'zone_key', 'weight_key'], 'cr_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_rates');
    }
};
