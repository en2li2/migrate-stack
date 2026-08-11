<?php

namespace App\Filament\Forms\Components;

use App\Models\IspCore\AddressCity;
use App\Models\IspCore\AddressDistrict;
use App\Models\IspCore\AddressNeighborhood;
use App\Models\IspCore\AddressStreet;
use App\Services\Addresses\StructuredAddressService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;

/**
 * Yapılandırılmış adres bileşeni (ISP Core'dan port; migrate legacy_customers
 * new_address_* kolonlarına yazar). Referans veri isp_panel address_* (SALT-OKUR).
 * Arama DB LIKE değil normalizeName ile — UAVT'deki Türkçe "İ" bileşik-nokta tuzağı.
 */
class StructuredAddressFields
{
    /** @return array<int, mixed> */
    public static function make(bool $required = false): array
    {
        return [
            Placeholder::make('address_section_heading')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div style="border-top:1px solid var(--gray-200,#e5e7eb);padding-top:14px;margin-top:4px;">'
                    .'<div style="font-size:13px;font-weight:700;color:var(--gray-950,#111827);">Adres Bilgileri</div>'
                    .'<div style="font-size:12px;color:var(--gray-500,#6b7280);margin-top:3px;">Konumdan binaya doğru ilerleyin; tam adres altta otomatik oluşur.</div>'
                    .'</div>'
                ))
                ->columnSpanFull(),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Select::make('new_address_city_id')
                    ->label('İl')
                    ->options(fn (): array => AddressCity::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => app(StructuredAddressService::class)->defaultCity()?->getKey())
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::searchByNormalizedName(AddressCity::query(), $search))
                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? AddressCity::query()->whereKey($value)->value('name') : null)
                    ->live()
                    ->required($required)
                    ->afterStateUpdated(function ($state, $set, $livewire): void {
                        $set('new_address_district_id', null);
                        $set('new_address_neighborhood_id', null);
                        $set('new_address_street_id', null);
                        $livewire->resetValidation();
                    }),
                Select::make('new_address_district_id')
                    ->label('İlçe')
                    ->options(fn ($get): array => filled($get('new_address_city_id'))
                        ? AddressDistrict::query()->where('city_id', $get('new_address_city_id'))->orderBy('name')->pluck('name', 'id')->all()
                        : [])
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, $get): array => filled($get('new_address_city_id'))
                        ? self::searchByNormalizedName(AddressDistrict::query()->where('city_id', $get('new_address_city_id')), $search)
                        : [])
                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? AddressDistrict::query()->whereKey($value)->value('name') : null)
                    ->live()
                    ->required($required)
                    ->disabled(fn ($get): bool => blank($get('new_address_city_id')))
                    ->afterStateUpdated(function ($state, $set, $livewire): void {
                        $set('new_address_neighborhood_id', null);
                        $set('new_address_street_id', null);
                        $livewire->resetValidation();
                    }),
                Select::make('new_address_neighborhood_id')
                    ->label('Mahalle')
                    ->options(fn ($get): array => filled($get('new_address_district_id'))
                        ? AddressNeighborhood::query()->where('district_id', $get('new_address_district_id'))->orderBy('name')->limit(50)->pluck('name', 'id')->all()
                        : [])
                    ->getSearchResultsUsing(fn (string $search, $get): array => filled($get('new_address_district_id'))
                        ? self::searchByNormalizedName(AddressNeighborhood::query()->where('district_id', $get('new_address_district_id')), $search)
                        : [])
                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? AddressNeighborhood::query()->whereKey($value)->value('name') : null)
                    ->searchable()
                    ->live()
                    ->required($required)
                    ->disabled(fn ($get): bool => blank($get('new_address_district_id')))
                    ->afterStateUpdated(function ($state, $set, $livewire): void {
                        $set('new_address_street_id', null);
                        $livewire->resetValidation();
                    }),
                Select::make('new_address_street_id')
                    ->label('Sokak / Cadde')
                    ->options(fn ($get): array => filled($get('new_address_neighborhood_id'))
                        ? AddressStreet::query()->where('neighborhood_id', $get('new_address_neighborhood_id'))->orderBy('name')->limit(50)->pluck('name', 'id')->all()
                        : [])
                    ->getSearchResultsUsing(fn (string $search, $get): array => filled($get('new_address_neighborhood_id'))
                        ? self::searchByNormalizedName(AddressStreet::query()->where('neighborhood_id', $get('new_address_neighborhood_id')), $search)
                        : [])
                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? AddressStreet::query()->whereKey($value)->value('name') : null)
                    ->searchable()
                    ->live()
                    ->disabled(fn ($get): bool => blank($get('new_address_neighborhood_id'))),
            ])->columnSpanFull(),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('new_address_building_no')->label('Bina No')->maxLength(60)->live(debounce: 600),
                Toggle::make('new_address_is_detached')
                    ->label('Müstakil')
                    ->live()
                    ->inline(false)
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $get): void {
                        $component->state(
                            filled($get('new_address_building_no'))
                            && blank($get('new_address_building_name'))
                            && blank($get('new_address_apartment_no'))
                        );
                    })
                    ->afterStateUpdated(function ($state, $set): void {
                        if ($state) {
                            $set('new_address_building_name', null);
                            $set('new_address_apartment_no', null);
                        }
                    }),
                TextInput::make('new_address_building_name')->label('Bina / Apartman adı')->maxLength(255)
                    ->live(debounce: 600)
                    ->visible(fn ($get): bool => ! $get('new_address_is_detached')),
                TextInput::make('new_address_apartment_no')->label('Daire No')->maxLength(60)
                    ->live(debounce: 600)
                    ->visible(fn ($get): bool => ! $get('new_address_is_detached')),
            ])->columnSpanFull(),
            TextInput::make('new_address_note')->label('Adres Notu')->maxLength(255)->columnSpanFull()->live(debounce: 600),
            Placeholder::make('new_address_preview')
                ->label('Adres önizleme')
                ->content(function ($get): HtmlString {
                    $address = app(StructuredAddressService::class)->buildFullAddress([
                        'new_address_city_id' => $get('new_address_city_id'),
                        'new_address_district_id' => $get('new_address_district_id'),
                        'new_address_neighborhood_id' => $get('new_address_neighborhood_id'),
                        'new_address_street_id' => $get('new_address_street_id'),
                        'new_address_building_name' => $get('new_address_building_name'),
                        'new_address_building_no' => $get('new_address_building_no'),
                        'new_address_apartment_no' => $get('new_address_apartment_no'),
                        'new_address_note' => $get('new_address_note'),
                    ]);

                    $text = $address ?: 'Adres seçimleri tamamlandığında burada görünecek.';

                    return new HtmlString(
                        '<div style="border:1px dashed var(--gray-300,#d1d5db);border-radius:10px;background:var(--gray-50,#f9fafb);padding:10px 12px;color:var(--gray-700,#374151);font-size:12.5px;line-height:1.45;">'
                        .e($text)
                        .'</div>'
                    );
                })
                ->columnSpanFull(),
        ];
    }

    /**
     * Kapsam sorgusunu isim üzerinde NORMALİZE ederek arar (DB LIKE değil).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, string>
     */
    public static function searchByNormalizedName($query, string $search): array
    {
        $needle = self::normalizeName($search);

        if ($needle === '') {
            return $query->orderBy('name')->limit(50)->pluck('name', 'id')->all();
        }

        return $query->orderBy('name')->get(['id', 'name'])
            ->filter(fn ($row): bool => str_contains(self::normalizeName((string) $row->name), $needle))
            ->take(50)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Adres araması için isim normalizasyonu: küçült + Türkçe harf katla +
     * bileşik aksan (U+0300–U+036F, "İ"→"i̇" gizli noktası) temizle.
     */
    public static function normalizeName(?string $s): string
    {
        $s = mb_strtolower((string) $s, 'UTF-8');
        $s = strtr($s, ['ç' => 'c', 'ğ' => 'g', 'ş' => 's', 'ü' => 'u', 'ö' => 'o', 'ı' => 'i', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
        $s = (string) preg_replace('/[\x{0300}-\x{036F}]/u', '', $s);
        $s = (string) preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim($s);
    }
}
