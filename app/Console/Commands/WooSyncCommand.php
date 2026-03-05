<?php

namespace App\Console\Commands;

use App\Models\WooShop;
use App\Services\WooCommerce\WooRevenueSyncService;
use Illuminate\Console\Command;
use Throwable;

class WooSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'woo:sync {--shop=* : WooShop IDs to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync WooCommerce daily revenue for one or more shops';

    /**
     * Execute the console command.
     */
    public function handle(WooRevenueSyncService $syncService): int
    {
        $shopIds = collect($this->option('shop'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        $shops = WooShop::query()
            ->when($shopIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $shopIds))
            ->orderBy('name')
            ->get();

        if ($shops->isEmpty()) {
            $this->warn('No WooShops matched the provided filters.');

            return self::SUCCESS;
        }

        $this->info('Syncing '.$shops->count().' WooShop(s)...');

        $ordersCount = 0;
        $failedShops = [];

        foreach ($shops as $index => $shop) {
            if (! $shop instanceof WooShop) {
                continue;
            }

            $shopNumber = $index + 1;

            try {
                $processedOrders = $syncService->syncShop($shop);
                $ordersCount += $processedOrders;

                $this->line("[{$shopNumber}/{$shops->count()}] {$shop->name}: synced {$processedOrders} eligible order(s)");
            } catch (Throwable $exception) {
                $failedShops[] = $shop->name;

                $this->error("[{$shopNumber}/{$shops->count()}] {$shop->name}: failed ({$exception->getMessage()})");
            }
        }

        $this->newLine();
        $this->info('Sync finished.');
        $this->line('Eligible orders processed: '.$ordersCount);
        $this->line('Shops attempted: '.$shops->count());

        if ($failedShops !== []) {
            $this->error('Failed shops: '.implode(', ', $failedShops));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
