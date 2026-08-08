<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migrate paneli müşteri aynası — ISP Core `customers` ile 1:1 (go-live push birebir)
// + migrate'e özel kolonlar: elle-düzeltme kilidi, düzenlenmiş adres sinyali, evrak JSON.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_customers', function (Blueprint $table): void {
            $table->id();

            // Kaynak izleme (legacy'den)
            $table->string('legacy_source')->default('proradiusmanager');
            $table->string('legacy_id')->nullable();
            $table->string('pppoe_username')->nullable()->index();
            $table->string('subscriber_number', 32)->nullable();

            // Kimlik / isim (Ad/Soyad ayrımı + birleşik name iki-yönlü)
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_title')->nullable();
            $table->string('customer_type')->default('individual'); // individual | corporate
            $table->string('national_id')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('tax_office')->nullable();
            // Kurumsal yetkili
            $table->string('authorized_first_name', 120)->nullable();
            $table->string('authorized_last_name', 120)->nullable();
            $table->string('authorized_national_id', 20)->nullable();

            // İletişim
            $table->string('phone')->nullable();
            $table->string('phone2')->nullable();
            $table->string('email')->nullable();

            // Adres — serbest + UAVT yapılandırılmış (ISP Core referansına eşlenecek)
            $table->text('address')->nullable();
            $table->unsignedBigInteger('address_city_id')->nullable();
            $table->unsignedBigInteger('address_district_id')->nullable();
            $table->unsignedBigInteger('address_neighborhood_id')->nullable();
            $table->unsignedBigInteger('address_street_id')->nullable();
            $table->string('address_building_name')->nullable();
            $table->string('address_building_no')->nullable();
            $table->string('address_apartment_no')->nullable();
            $table->text('structured_address_text')->nullable();
            // "Düzenlendi" sinyali — yalnız elle düzenlemede dolar, sync EZMEZ
            $table->text('new_address_text')->nullable();

            // Abonelik / paket
            $table->string('status')->default('active');
            $table->unsignedBigInteger('service_package_id')->nullable();
            $table->string('package_name')->nullable();
            $table->unsignedInteger('download_rate')->nullable();
            $table->unsignedInteger('upload_rate')->nullable();
            $table->dateTime('subscription_ends_at')->nullable();
            $table->string('static_ip')->nullable();

            // Vade (Fatura Kesim Zamanı) — ISP Core ile 1:1
            $table->string('invoice_timing_mode', 24)->nullable(); // delayed|immediate|advance
            $table->unsignedSmallInteger('invoice_timing_grace_hours')->nullable();
            $table->unsignedSmallInteger('invoice_timing_advance_days')->nullable();

            // Evrak (JSON) — {identity_front, identity_back, contract, ...}; null-safe kullanılacak
            $table->json('documents')->nullable();

            // Migrate omurgası
            $table->json('locked_fields')->nullable();   // elle-düzeltme kilidi: bu alanları sync ezmez
            $table->json('legacy_payload')->nullable();   // ham legacy satırı
            $table->json('sync_issues')->nullable();
            $table->timestamp('legacy_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
            $table->index('subscription_ends_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_customers');
    }
};
