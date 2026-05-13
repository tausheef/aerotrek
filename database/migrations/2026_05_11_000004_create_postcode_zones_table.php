<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postcode_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                  ->constrained('rate_uploads')
                  ->cascadeOnDelete();
            // AU_SELF | AU_DPEX_SELF | NZ_SELF
            $table->string('carrier', 20);
            // postcode stored without leading zeros (e.g. "3000", "505")
            $table->string('postcode', 10);
            // zone1 | zone2 | ... matching carrier_rates.zone_key
            $table->string('zone_key', 15);

            $table->index(['upload_id', 'carrier', 'postcode'], 'pz_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postcode_zones');
    }
};
