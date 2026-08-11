<?php

namespace App\Models\IspCore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddressStreet extends Model
{
    protected $connection = 'isp_panel';

    protected $table = 'address_streets';

    protected $guarded = [];

    public $timestamps = true;

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(AddressNeighborhood::class, 'neighborhood_id');
    }
}
