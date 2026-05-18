<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('shiprocket_pickup_id')->nullable()->after('phone');
            $table->string('shiprocket_pickup_name')->nullable()->after('shiprocket_pickup_id');
            $table->boolean('pickup_verified')->default(false)->after('shiprocket_pickup_name');
            $table->timestamp('pickup_verified_at')->nullable()->after('pickup_verified');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn([
                'shiprocket_pickup_id',
                'shiprocket_pickup_name',
                'pickup_verified',
                'pickup_verified_at',
            ]);
        });
    }
};
