<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('invoice_currency', 10)->nullable()->after('invoice_date');
            $table->string('terms_of_sale', 20)->nullable()->after('invoice_currency');
            $table->string('csb_type', 10)->nullable()->after('terms_of_sale');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['invoice_currency', 'terms_of_sale', 'csb_type']);
        });
    }
};
