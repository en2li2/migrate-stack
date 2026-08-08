<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Özel Fiyat aynası — ISP Core `customer_special_package_prices` ile hizalı.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_special_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legacy_customer_id')->nullable()->constrained('legacy_customers')->nullOnDelete();
            $table->foreignId('legacy_package_id')->nullable()->constrained('legacy_packages')->nullOnDelete();
            $table->string('package_name')->nullable();

            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('TRY');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->json('locked_fields')->nullable();
            $table->string('legacy_source')->default('proradiusmanager');
            $table->string('legacy_id')->nullable();
            $table->timestamp('legacy_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_special_prices');
    }
};
