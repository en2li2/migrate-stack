<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyCustomer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'documents' => 'array',
        'locked_fields' => 'array',
        'legacy_payload' => 'array',
        'sync_issues' => 'array',
        'subscription_ends_at' => 'datetime',
        'legacy_synced_at' => 'datetime',
    ];

    // Elle düzeltilebilen ve sync tarafından EZİLMEMESİ gereken alanlar.
    public const EDITABLE_FIELDS = [
        'first_name', 'last_name', 'company_title', 'customer_type',
        'national_id', 'tax_number', 'tax_office',
        'authorized_first_name', 'authorized_last_name', 'authorized_national_id',
        'phone', 'phone2', 'email',
        'address', 'new_address_text',
        'address_city_id', 'address_district_id', 'address_neighborhood_id', 'address_street_id',
        'address_building_name', 'address_building_no', 'address_apartment_no', 'structured_address_text',
        'invoice_timing_mode', 'invoice_timing_grace_hours', 'invoice_timing_advance_days',
        'documents',
    ];

    protected static function booted(): void
    {
        // Ad/Soyad <-> name iki yönlü senkron.
        static::saving(function (LegacyCustomer $c): void {
            if ($c->customer_type === 'corporate') {
                // Kurumsalda unvan tek parça, first/last boş.
                if (filled($c->company_title)) {
                    $c->name = $c->company_title;
                }
            } else {
                if (filled($c->first_name) || filled($c->last_name)) {
                    $c->name = trim(($c->first_name ?? '').' '.($c->last_name ?? ''));
                } elseif (filled($c->name) && blank($c->first_name) && blank($c->last_name)) {
                    // name -> ad/soyad böl: son kelime = soyad
                    $parts = preg_split('/\s+/', trim($c->name)) ?: [];
                    if (count($parts) > 1) {
                        $c->last_name = array_pop($parts);
                        $c->first_name = implode(' ', $parts);
                    } else {
                        $c->first_name = $c->name;
                    }
                }
            }
        });
    }

    // Bir alan elle kilitli mi (sync ezmemeli)?
    public function isFieldLocked(string $field): bool
    {
        return in_array($field, (array) ($this->locked_fields ?? []), true);
    }

    public function lockField(string $field): void
    {
        $locked = (array) ($this->locked_fields ?? []);
        if (! in_array($field, $locked, true)) {
            $locked[] = $field;
            $this->locked_fields = array_values($locked);
        }
    }

    // Evrak var mı — null-safe (JSON_LENGTH(null)=1 tuzağına düşme).
    public function hasDocument(string $key): bool
    {
        $docs = $this->documents ?? [];
        $val = $docs[$key] ?? null;

        return is_array($val) ? count($val) > 0 : filled($val);
    }
}
