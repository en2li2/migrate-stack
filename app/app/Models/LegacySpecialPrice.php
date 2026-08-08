<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacySpecialPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'locked_fields' => 'array',
        'legacy_synced_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LegacyCustomer::class, 'legacy_customer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(LegacyPackage::class, 'legacy_package_id');
    }
}
