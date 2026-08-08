<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket aynası — ISP Core `service_packages` ile hizalı.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_source')->default('proradiusmanager');
            $table->string('legacy_id')->nullable();

            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedInteger('download_rate')->nullable();
            $table->unsignedInteger('upload_rate')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('duration_type')->nullable();
            $table->unsignedInteger('duration_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('radius_group_name')->nullable();
            $table->string('framed_pool')->nullable();
            $table->unsignedInteger('simultaneous_use')->nullable();
            $table->text('description')->nullable();

            $table->json('locked_fields')->nullable();
            $table->json('legacy_payload')->nullable();
            $table->timestamp('legacy_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_packages');
    }
};
