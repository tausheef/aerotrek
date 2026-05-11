<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shiprocket_rates', function (Blueprint $table) {
            // Nullable so existing rows (seeded before this feature) still work
            $table->foreignId('upload_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('rate_uploads')
                  ->cascadeOnDelete();

            $table->index('upload_id');
        });
    }

    public function down(): void
    {
        Schema::table('shiprocket_rates', function (Blueprint $table) {
            $table->dropForeign(['upload_id']);
            $table->dropColumn('upload_id');
        });
    }
};
