<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("UPDATE shipments SET goods_type = 'Document'     WHERE goods_type = 'Dox'");
        DB::statement("UPDATE shipments SET goods_type = 'Non-Document' WHERE goods_type = 'NDox'");
        DB::statement("ALTER TABLE shipments MODIFY goods_type ENUM('Document', 'Non-Document') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE shipments SET goods_type = 'Dox'  WHERE goods_type = 'Document'");
        DB::statement("UPDATE shipments SET goods_type = 'NDox' WHERE goods_type = 'Non-Document'");
        DB::statement("ALTER TABLE shipments MODIFY goods_type ENUM('Dox', 'NDox') NOT NULL");
    }
};
