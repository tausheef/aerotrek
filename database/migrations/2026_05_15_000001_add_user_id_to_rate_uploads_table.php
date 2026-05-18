<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_uploads', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->after('uploaded_by');
            $table->index(['user_id', 'status'], 'ru_user_status');
        });
    }

    public function down(): void
    {
        Schema::table('rate_uploads', function (Blueprint $table) {
            $table->dropIndex('ru_user_status');
            $table->dropColumn('user_id');
        });
    }
};
