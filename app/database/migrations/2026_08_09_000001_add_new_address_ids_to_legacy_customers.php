<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yapılandırılmış adres kolonları (isp_panel address_* referansına işaret eder;
 * cross-DB FK olmaz, sadece nullable). new_address_text zaten var.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            foreach ([
                'new_address_city_id', 'new_address_district_id',
                'new_address_neighborhood_id', 'new_address_street_id',
            ] as $col) {
                if (! Schema::hasColumn('legacy_customers', $col)) {
                    $table->unsignedBigInteger($col)->nullable();
                }
            }
            foreach ([
                'new_address_building_no', 'new_address_building_name',
                'new_address_apartment_no', 'new_address_note',
            ] as $col) {
                if (! Schema::hasColumn('legacy_customers', $col)) {
                    $table->string($col, 255)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            foreach ([
                'new_address_city_id', 'new_address_district_id',
                'new_address_neighborhood_id', 'new_address_street_id',
                'new_address_building_no', 'new_address_building_name',
                'new_address_apartment_no', 'new_address_note',
            ] as $col) {
                if (Schema::hasColumn('legacy_customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
