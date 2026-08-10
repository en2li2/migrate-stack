<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CRM nas_devices ile birebir tasarım (sonra push edince sorun çıkmasın) + legacy izleme.
    public function up(): void
    {
        Schema::create('legacy_nas', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_source')->default('proradiusmanager');
            $table->string('legacy_id')->nullable();

            $table->string('name')->nullable();
            $table->string('shortname')->nullable();
            $table->string('nas_ip_address')->nullable();
            $table->string('secret')->nullable();
            $table->string('type')->nullable();
            $table->string('ports')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();

            $table->boolean('api_enabled')->default(false);
            $table->string('api_host')->nullable();
            $table->unsignedInteger('api_port')->nullable();
            $table->string('api_username')->nullable();
            $table->string('api_password')->nullable();
            $table->boolean('api_tls')->default(false);
            $table->string('api_status')->nullable();
            $table->timestamp('api_last_checked_at')->nullable();

            $table->json('locked_fields')->nullable();
            $table->json('legacy_payload')->nullable();
            $table->timestamp('legacy_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_nas');
    }
};
