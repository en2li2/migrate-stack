<?php

namespace App\Filament\Resources\Evrak\Pages;

use App\Filament\Resources\Evrak\EvrakResource;
use App\Models\LegacyCustomer;
use App\Services\Identity\IdentityVerificationService;
use App\Services\Identity\TaxCertificateVerificationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Evrak Yükle — modal yerine tam sayfa. Form modele BAĞLI DEĞİL (statePath 'data'):
 * FileUpload dosyaları diske yazılır. Doğrulama YÜKLEME ANINDA (live) koşar —
 * kimlik ön+arka (ve kurumsalda vergi levhası) yüklenince, KAYDETMEDEN, sonuç
 * formun altında kutu olarak gösterilir. Kaydet'te tekrar koşar; sert hatada yazmaz.
 */
class UploadEvrak extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EvrakResource::class;

    protected string $view = 'filament.resources.evrak.pages.upload-evrak';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Sert doğrulama hatası — form altında kırmızı kutu. */
    public ?string $evrakError = null;

    /** Yumuşak uyarılar — form altında sarı kutu. */
    /** @var list<string> */
    public array $evrakWarnings = [];

    /** Olumlu onaylar — form altında yeşil kutu ("doğrulandı, eşleşti"). */
    /** @var list<string> */
    public array $evrakOk = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['documents' => $this->record->documents ?? []]);
    }

    public function getTitle(): string
    {
        return ($this->record->name ?: $this->record->pppoe_username).' — Evraklar';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(EvrakResource::documentComponents($this->record))
            ->statePath('data')
            ->columns(2);
    }

    /**
     * Yükleme anında (live) çağrılır — geçici dosyalar üzerinden doğrular,
     * KAYDETMEZ; sadece evrakError/evrakWarnings'i günceller (alttaki kutu).
     */
    public function verifyDocuments(): void
    {
        $this->runVerification($this->data['documents'] ?? []);
    }

    /**
     * Kimlik (yetkili) + kurumsalda vergi levhası doğrular; sonucu
     * evrakError/evrakWarnings'e yazar. Sert hata varsa false döner.
     */
    private function runVerification(array $documents): bool
    {
        $this->evrakError = null;
        $this->evrakWarnings = [];
        $this->evrakOk = [];

        $r = $this->record;
        $isCompany = $r->customer_type === 'company';
        $cardTc = (string) ($isCompany ? $r->authorized_national_id : $r->national_id);
        $cardFirst = (string) ($isCompany ? $r->authorized_first_name : ($r->first_name ?: ''));
        $cardLast = (string) ($isCompany ? $r->authorized_last_name : ($r->last_name ?: $r->name));

        $front = $documents['identity_front'] ?? null;
        $back = $documents['identity_back'] ?? null;

        // Kimlik OCR ağır → yalnız İKİ YÜZ de yüklüyken koş (tek yüzde boşa OCR yok).
        if (filled($front) && filled($back)) {
            $verification = app(IdentityVerificationService::class)->verifyAgainstCard(
                $front, $back, $cardTc, $cardFirst, $cardLast,
            );

            if ($verification['blocked']) {
                $this->evrakError = 'Kimlik doğrulanamadı — '.$verification['reason'];

                return false;
            }

            if (! empty($verification['warning'])) {
                $this->evrakWarnings[] = $verification['warning'];
            } else {
                $id = $verification['identity'];
                $name = trim(($id['given'] ?? '').' '.($id['surname'] ?? ''));
                if ($name !== '' || ! empty($id['tc'])) {
                    $this->evrakOk[] = 'Kimlik doğrulandı — '.($name !== '' ? $name : '(ad okunamadı)').(! empty($id['tc']) ? ', TC '.$id['tc'] : '').' kayıtla eşleşti.';
                }
            }
        } elseif (filled($front) || filled($back)) {
            $this->evrakWarnings[] = 'Kimlik doğrulaması için ön ve arka yüzün ikisi de yüklenmeli.';
        }

        // Kurumsal: vergi levhası yalnız yüklüyken doğrula.
        if ($isCompany && filled($documents['tax_certificate'] ?? null)) {
            $tax = app(TaxCertificateVerificationService::class)->verifyAgainstCard(
                $documents['tax_certificate'],
                (string) ($r->tax_number ?: $r->national_id),
                (string) $r->tax_office,
                (string) ($r->company_title ?: $r->name),
            );

            if ($tax['blocked']) {
                $this->evrakError = 'Vergi levhası doğrulanamadı — '.$tax['reason'];

                return false;
            }

            if (! empty($tax['warning'])) {
                $this->evrakWarnings[] = $tax['warning'];
            } else {
                $this->evrakOk[] = 'Vergi levhası doğrulandı'.(! empty($tax['vkn']) ? ' — VKN '.$tax['vkn'] : '').', ünvan ve vergi dairesi eşleşti.';
            }
        }

        return true;
    }

    public function save(): void
    {
        // getState() FileUpload dosyalarını diske yazar + kesin yolları verir.
        $data = $this->form->getState();
        $documents = $data['documents'] ?? [];

        $ok = $this->runVerification($documents);

        if (! $ok) {
            Notification::make()
                ->title('Evrak doğrulanamadı — kaydedilmedi')
                ->body($this->evrakError)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $this->record->update(['documents' => $documents]);

        if ($this->evrakWarnings !== []) {
            Notification::make()
                ->title('Evraklar kaydedildi — kontrol notu')
                ->body(implode(' ', $this->evrakWarnings))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Evraklar kaydedildi')
            ->body(($this->record->name ?: $this->record->pppoe_username).' — evrak durumu güncellendi.')
            ->success()
            ->send();

        $this->redirect(EvrakResource::getUrl('index'));
    }
}