<?php

namespace App\Console\Commands;

use App\Services\LegacyCustomerSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class LegacySyncCommand extends Command
{
    protected $signature = 'legacy:sync';

    protected $description = 'Uzak legacy radius DB\'den müşteri + paket + kullanım verisini migrate DB\'ye senkronlar.';

    public function handle(LegacyCustomerSyncService $service): int
    {
        Cache::put('legacy_sync:status', ['state' => 'running', 'started_at' => now()->toIso8601String()], now()->addHour());
        $t0 = microtime(true);

        try {
            $r = $service->sync();
        } catch (\Throwable $e) {
            Cache::put('legacy_sync:status', ['state' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()->toIso8601String()], now()->addDay());
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $r['seconds'] = round(microtime(true) - $t0, 1);
        $r['state'] = 'done';
        $r['finished_at'] = now()->toIso8601String();
        Cache::put('legacy_sync:status', $r, now()->addDay());

        $this->info('Sync OK: '.json_encode($r));

        return self::SUCCESS;
    }
}
