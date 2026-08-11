<?php

namespace App\Models\IspCore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddressNeighborhood extends Model
{
    protected $connection = 'isp_panel';

    protected $table = 'address_neighborhoods';

    protected $guarded = [];

    public $timestamps = true;

    public function district(): BelongsTo
    {
        return $this->belongsTo(AddressDistrict::class, 'district_id');
    }

    public function streets(): HasMany
    {
        return $this->hasMany(AddressStreet::class, 'neighborhood_id');
    }
}
