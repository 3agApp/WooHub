<?php

namespace App\Services\WooCommerce;

use App\Models\WooDailyRevenue;
use App\Models\WooShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class WooRevenueSyncService
{
    /**
     * @return array{success:bool,message:string}
     */
    public function testConnection(WooShop $shop): array
    {
        try {
            $response = Http::baseUrl($this->normalizeBaseUrl($shop->url))
                ->withBasicAuth($shop->consumer_key, $shop->consumer_secret)
                ->acceptJson()
                ->timeout(20)
                ->retry(1, 300)
                ->get('/wp-json/wc/v3/orders', [
                    'per_page' => 1,
                    'page' => 1,
                ])
                ->throw();

            $totalOrders = (int) ($response->header('X-WP-Total') ?? 0);

            return [
                'success' => true,
                'message' => 'Connection successful. Accessible orders: '.$totalOrders,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => str($exception->getMessage())->limit(500)->toString(),
            ];
        }
    }

    /**
     * @return array{shops_count:int,orders_count:int,failed_shops:list<string>}
     */
    public function syncMany(Collection $shops): array
    {
        $failedShops = [];
        $ordersCount = 0;

        foreach ($shops as $shop) {
            try {
                $ordersCount += $this->syncShop($shop);
            } catch (Throwable) {
                $failedShops[] = $shop->name;
            }
        }

        return [
            'shops_count' => $shops->count(),
            'orders_count' => $ordersCount,
            'failed_shops' => $failedShops,
        ];
    }

    public function syncShop(WooShop $shop): int
    {
        $now = now();
        $ordersCount = 0;
        $currentPage = 1;
        $totalPages = 1;
        $dailyTotals = [];

        try {
            do {
                $response = Http::baseUrl($this->normalizeBaseUrl($shop->url))
                    ->withBasicAuth($shop->consumer_key, $shop->consumer_secret)
                    ->acceptJson()
                    ->timeout(30)
                    ->retry(2, 500)
                    ->get('/wp-json/wc/v3/orders', [
                        'page' => $currentPage,
                        'per_page' => 100,
                        'orderby' => 'date',
                        'order' => 'desc',
                    ])
                    ->throw();

                $orders = $response->json();

                if (! is_array($orders)) {
                    break;
                }

                [$pageTotals, $pageOrdersCount] = $this->aggregateDailyRevenue($shop, $orders);

                foreach ($pageTotals as $key => $row) {
                    if (! isset($dailyTotals[$key])) {
                        $dailyTotals[$key] = $row;

                        continue;
                    }

                    $dailyTotals[$key]['revenue_total'] += $row['revenue_total'];
                    $dailyTotals[$key]['orders_count'] += $row['orders_count'];
                }

                $ordersCount += $pageOrdersCount;

                $totalPages = max((int) ($response->header('X-WP-TotalPages') ?? 1), 1);
                $currentPage++;
            } while ($currentPage <= $totalPages);

            if ($dailyTotals !== []) {
                $payload = collect($dailyTotals)
                    ->values()
                    ->map(fn (array $row): array => [
                        ...$row,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                WooDailyRevenue::query()->upsert(
                    $payload,
                    ['woo_shop_id', 'revenue_date', 'currency'],
                    ['revenue_total', 'orders_count', 'updated_at'],
                );
            }

            $shop->forceFill([
                'last_synced_at' => $now,
                'last_sync_status' => 'success',
                'last_sync_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $shop->forceFill([
                'last_synced_at' => $now,
                'last_sync_status' => 'failed',
                'last_sync_error' => str($exception->getMessage())->limit(5000)->toString(),
            ])->save();

            throw $exception;
        }

        return $ordersCount;
    }

    /**
     * @param  list<mixed>  $orders
     * @return array{0:array<string, array<string, mixed>>,1:int}
     */
    private function aggregateDailyRevenue(WooShop $shop, array $orders): array
    {
        $dailyTotals = [];
        $ordersCount = 0;

        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }

            $status = strtolower((string) Arr::get($order, 'status', 'unknown'));

            if (! in_array($status, ['processing', 'completed'], true)) {
                continue;
            }

            $orderCreatedAt = $this->parseDate(Arr::get($order, 'date_created_gmt') ?? Arr::get($order, 'date_created'));

            if (! $orderCreatedAt) {
                continue;
            }

            $currency = trim((string) Arr::get($order, 'currency', 'CHF'));
            $currency = $currency !== '' ? $currency : 'CHF';

            $key = $orderCreatedAt->toDateString().'|'.$currency;

            if (! isset($dailyTotals[$key])) {
                $dailyTotals[$key] = [
                    'woo_shop_id' => $shop->getKey(),
                    'revenue_date' => $orderCreatedAt->toDateString(),
                    'currency' => $currency,
                    'revenue_total' => 0.0,
                    'orders_count' => 0,
                ];
            }

            $dailyTotals[$key]['revenue_total'] += (float) Arr::get($order, 'total', 0);
            $dailyTotals[$key]['orders_count'] += 1;
            $ordersCount++;
        }

        return [$dailyTotals, $ordersCount];
    }

    private function normalizeBaseUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
