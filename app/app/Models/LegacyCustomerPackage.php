<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyCustomerPackage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status_code' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LegacyCustomer::class, 'legacy_customer_id');
    }
}
