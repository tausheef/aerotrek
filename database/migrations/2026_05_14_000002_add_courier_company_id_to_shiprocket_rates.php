<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shiprocket_rates', function (Blueprint $table) {
            $table->unsignedInteger('courier_company_id')->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('shiprocket_rates', function (Blueprint $table) {
            $table->dropColumn('courier_company_id');
        });
    }
};
