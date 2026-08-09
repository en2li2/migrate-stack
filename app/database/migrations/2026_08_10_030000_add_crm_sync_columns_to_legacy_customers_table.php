<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('crm_customer_id')->nullable()->after('legacy_synced_at');
            $table->string('crm_subscriber_number')->nullable()->after('crm_customer_id');
            $table->string('crm_sync_status')->nullable()->after('crm_subscriber_number');
            $table->text('crm_sync_message')->nullable()->after('crm_sync_status');
            $table->timestamp('crm_synced_at')->nullable()->after('crm_sync_message');
            $table->index('crm_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_customers', function (Blueprint $table): void {
            $table->dropIndex(['crm_customer_id']);
            $table->dropColumn([
                'crm_customer_id',
                'crm_subscriber_number',
                'crm_sync_status',
                'crm_sync_message',
                'crm_synced_at',
            ]);
        });
    }
};
