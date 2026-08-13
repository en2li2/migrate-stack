<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            // Operatorun "Ucretsiz" isaretledigi musteriler (faturasiz internet).
            $table->boolean('is_free')->default(false)->after('bilgi');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            $table->dropColumn('is_free');
        });
    }
};
