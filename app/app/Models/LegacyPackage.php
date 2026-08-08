<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyPackage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'locked_fields' => 'array',
        'legacy_payload' => 'array',
        'legacy_synced_at' => 'datetime',
    ];

    public const EDITABLE_FIELDS = ['name', 'code', 'price', 'currency', 'download_rate', 'upload_rate', 'description', 'is_active'];
}
