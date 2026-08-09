<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_customer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legacy_customer_id')->constrained('legacy_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_queue_id')->unique(); // raduserqueue.id
            $table->tinyInteger('status_code'); // 0=bekleyen, 1=aktif, 2=geçmiş
            $table->string('package_name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->index(['legacy_customer_id', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_customer_packages');
    }
};
