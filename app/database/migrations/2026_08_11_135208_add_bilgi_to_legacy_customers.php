<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table) {
            // Operatörün elle doldurduğu serbest not — aynı TC/VKN altındaki
            // farklı abonelikleri ayırt etmek için (ev / iş yeri / 2. hat vb).
            // Sync tarafından ASLA yazılmaz/ezilmez (migrate-only alan).
            $table->text('bilgi')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table) {
            $table->dropColumn('bilgi');
        });
    }
};