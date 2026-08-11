<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\StructuredAddressFields;
use App\Models\LegacyCustomer;
use App\Services\AddressMatching\AddressMatchingWizardService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Adres Eşleştirme (sihirbaz): müşteriler analiz sırasıyla TEK TEK gelir —
 * önce otomatik-hazır, sonra öneri-kontrol, en son elle-gerekli. Motorun önerisi
 * forma DOLU gelir; operatör düzeltir/onaylar. Onaysız hiçbir şey yazılmaz.
 */
class AddressMatchingWizard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Veri';

    protected static ?string $navigationLabel = 'Adres Eşleştirme';

    protected static ?string $title = 'Adres Eşleştirme';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.address-matching-wizard';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?int $customerId = null;

    /** @var array<string, mixed> */
    public array $customerInfo = [];

    /** @var array<int, string> */
    public array $skipped = [];

    /** @var array<string, mixed> */
    public array $progress = [];

    public bool $finished = false;

    public function mount(): void
    {
        $this->loadNext();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(StructuredAddressFields::make())->statePath('data');
    }

    private function service(): AddressMatchingWizardService
    {
        return app(AddressMatchingWizardService::class);
    }

    public function loadNext(): void
    {
        $service = $this->service();
        $next = $service->next($this->skipped);
        $this->progress = $service->progress($this->skipped);

        if ($next === null) {
            $this->finished = true;
            $this->customerId = null;
            $this->customerInfo = [];
            $this->form->fill([]);

            return;
        }

        /** @var LegacyCustomer $customer */
        $customer = $next['customer'];
        $match = $next['match'];

        $this->finished = false;
        $this->customerId = (int) $customer->id;
        $this->customerInfo = [
            'name' => (string) ($customer->name ?: '— ad yok —'),
            'pppoe' => (string) $customer->pppoe_username,
            'identity' => $customer->customer_type === 'company' ? (string) ($customer->tax_number ?: '—') : (string) ($customer->national_id ?: '—'),
            'identity_label' => $customer->customer_type === 'company' ? 'Vergi No' : 'TC Kimlik',
            'phone' => (string) ($customer->phone ?: '—'),
            'legacy_address' => (string) ($customer->address ?: '—'),
            'status' => (string) $match['status'],
            'match_type' => (string) $match['match_type'],
            'levenshtein' => $match['levenshtein'],
            'candidate' => (string) $match['candidate'],
            'suggestion' => $service->label($match),
        ];

        $this->form->fill($service->formState($match));
    }

    public function approve(): void
    {
        $customer = $this->customerId ? LegacyCustomer::find($this->customerId) : null;
        if (! $customer) {
            $this->loadNext();

            return;
        }

        $this->service()->approve($customer, $this->form->getState());

        Notification::make()
            ->title('Adres eşleşti')
            ->body(($customer->name ?: $customer->pppoe_username).' — adres müşteri kartına işlendi.')
            ->success()
            ->send();

        $this->loadNext();
    }

    public function skip(): void
    {
        if (filled($this->customerInfo['pppoe'] ?? null)) {
            $this->skipped[] = (string) $this->customerInfo['pppoe'];
        }
        $this->loadNext();
    }

    public function resetSkipped(): void
    {
        $this->skipped = [];
        $this->loadNext();
    }
}
