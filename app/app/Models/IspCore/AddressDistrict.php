<?php

namespace App\Models\IspCore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddressDistrict extends Model
{
    protected $connection = 'isp_panel';

    protected $table = 'address_districts';

    protected $guarded = [];

    public $timestamps = true;

    public function city(): BelongsTo
    {
        return $this->belongsTo(AddressCity::class, 'city_id');
    }

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(AddressNeighborhood::class, 'district_id');
    }
}
