<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Legacy PPPoE şifresi (userinfo.password) — CRM PppoeAccount + radcheck için gerekli.
    public function up(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            $table->string('pppoe_password')->nullable()->after('pppoe_username');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            $table->dropColumn('pppoe_password');
        });
    }
};
