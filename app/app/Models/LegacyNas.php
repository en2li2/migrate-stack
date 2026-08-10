<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyNas extends Model
{
    protected $table = 'legacy_nas';

    protected $guarded = ['id'];

    protected $casts = [
        'api_enabled' => 'boolean',
        'api_tls' => 'boolean',
        'api_port' => 'integer',
        'api_last_checked_at' => 'datetime',
        'locked_fields' => 'array',
        'legacy_payload' => 'array',
        'legacy_synced_at' => 'datetime',
    ];

    // Elle düzenlenebilen alanlar → değişince kilitlenir, Legacy Sync ezmez.
    public const EDITABLE_FIELDS = [
        'name', 'shortname', 'nas_ip_address', 'secret', 'type', 'ports',
        'status', 'description', 'api_enabled', 'api_host', 'api_port',
        'api_username', 'api_password', 'api_tls',
    ];
}
