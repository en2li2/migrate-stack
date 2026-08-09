<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyCustomerUsage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'session_time' => 'integer',
            'download_bytes' => 'integer',
            'upload_bytes' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LegacyCustomer::class, 'legacy_customer_id');
    }
}
