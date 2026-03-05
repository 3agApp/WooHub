<?php

namespace App\Console\Commands;

use App\Models\WooShop;
use App\Services\WooCommerce\WooOrderSyncService;
use Illuminate\Console\Command;

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
    protected $description = 'Sync WooCommerce orders for one or more shops';

    /**
     * Execute the console command.
     */
    public function handle(WooOrderSyncService $syncService): int
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

        $result = $syncService->syncMany($shops);

        $this->newLine();
        $this->info('Sync finished.');
        $this->line('Orders processed: '.$result['orders_count']);
        $this->line('Shops attempted: '.$result['shops_count']);

        if ($result['failed_shops'] !== []) {
            $this->error('Failed shops: '.implode(', ', $result['failed_shops']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
