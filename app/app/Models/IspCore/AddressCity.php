<?php

namespace App\Models\IspCore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ISP Core (isp_panel DB) UAVT il tablosu — migrate panelinde SALT-OKUR referans.
 * Yazma migrate panelinden yapılmaz; veri isp:import-address-dataset ile dolar.
 */
class AddressCity extends Model
{
    protected $connection = 'isp_panel';

    protected $table = 'address_cities';

    protected $guarded = [];

    public $timestamps = true;

    public function districts(): HasMany
    {
        return $this->hasMany(AddressDistrict::class, 'city_id');
    }
}
