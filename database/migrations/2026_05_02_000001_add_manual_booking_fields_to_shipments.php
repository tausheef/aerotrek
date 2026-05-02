<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('booking_type', ['auto', 'manual'])->default('auto')->after('aerotrek_id');
            $table->text('notes')->nullable()->after('reason_for_export');
            $table->text('rejection_reason')->nullable()->after('notes');

            // Make carrier-related columns nullable — manual bookings don't have these at submission time
            $table->string('platform')->nullable()->change();
            $table->string('carrier')->nullable()->change();
            $table->string('service_code')->nullable()->change();
            $table->string('service_name')->nullable()->change();
            $table->string('network')->nullable()->change();
            $table->decimal('price', 10, 2)->nullable()->change();
        });

        // Extend status enum to include manual booking statuses
        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM(
            'pending_acceptance',
            'accepted',
            'rejected',
            'pending',
            'booked',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
            'failed'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['booking_type', 'notes', 'rejection_reason']);

            $table->string('platform')->nullable(false)->change();
            $table->string('carrier')->nullable(false)->change();
            $table->string('service_code')->nullable(false)->change();
            $table->string('service_name')->nullable(false)->change();
            $table->string('network')->nullable(false)->change();
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });

        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM(
            'pending',
            'booked',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
            'failed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
