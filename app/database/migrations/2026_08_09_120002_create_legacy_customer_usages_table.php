<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_customer_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legacy_customer_id')->constrained('legacy_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_radacct_id')->unique(); // radacct.radacctid
            $table->dateTime('started_at')->nullable();
            $table->dateTime('stopped_at')->nullable();
            $table->unsignedBigInteger('session_time')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->string('framed_ip', 64)->nullable();
            $table->string('terminate_cause', 64)->nullable();
            $table->timestamps();
            $table->index(['legacy_customer_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_customer_usages');
    }
};
