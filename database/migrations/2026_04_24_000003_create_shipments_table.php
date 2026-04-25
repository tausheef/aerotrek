<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('aerotrek_id')->unique();
            $table->string('platform');
            $table->string('platform_ref_id')->nullable();
            $table->string('user_id')->index();
            $table->string('awb_no')->nullable()->index();
            $table->string('tracking_no')->nullable();
            $table->string('carrier');
            $table->string('service_code');
            $table->string('service_name');
            $table->string('network');
            $table->enum('status', ['pending', 'booked', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed'])->default('pending');
            $table->enum('goods_type', ['Dox', 'NDox']);
            $table->string('label_url')->nullable();
            $table->string('invoice_url')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('chargeable_weight', 8, 2)->nullable();
            $table->json('sender');
            $table->json('receiver');
            $table->json('packages');
            $table->json('products');
            $table->string('invoice_no')->nullable();
            $table->string('invoice_date')->nullable();
            $table->string('duty_tax', 10)->nullable();
            $table->string('reason_for_export')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('wallet_transaction_id')->nullable();
            $table->json('overseas_response')->nullable();
            $table->json('tracking_events')->nullable();
            $table->timestamp('tracking_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
