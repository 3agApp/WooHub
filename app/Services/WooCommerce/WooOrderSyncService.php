<?php

namespace App\Services\WooCommerce;

use App\Models\WooOrder;
use App\Models\WooShop;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class WooOrderSyncService
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

                $payload = collect($orders)
                    ->filter(fn (mixed $order): bool => is_array($order) && Arr::has($order, 'id'))
                    ->map(fn (array $order): array => $this->mapOrderPayload($shop, $order, $now))
                    ->values()
                    ->all();

                if ($payload !== []) {
                    WooOrder::query()->upsert(
                        $payload,
                        ['woo_shop_id', 'external_order_id'],
                        [
                            'order_number',
                            'status',
                            'currency',
                            'total',
                            'customer_name',
                            'customer_email',
                            'order_created_at',
                            'order_paid_at',
                            'updated_at',
                        ],
                    );

                    $ordersCount += count($payload);
                }

                $totalPages = max((int) ($response->header('X-WP-TotalPages') ?? 1), 1);
                $currentPage++;
            } while ($currentPage <= $totalPages);

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
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function mapOrderPayload(WooShop $shop, array $order, CarbonInterface $now): array
    {
        $customerName = trim((string) Arr::get($order, 'billing.first_name').' '.(string) Arr::get($order, 'billing.last_name'));

        return [
            'woo_shop_id' => $shop->getKey(),
            'external_order_id' => (int) Arr::get($order, 'id'),
            'order_number' => (string) (Arr::get($order, 'number') ?? Arr::get($order, 'id')),
            'status' => (string) Arr::get($order, 'status', 'unknown'),
            'currency' => Arr::get($order, 'currency') ? (string) Arr::get($order, 'currency') : null,
            'total' => (float) Arr::get($order, 'total', 0),
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_email' => Arr::get($order, 'billing.email') ? (string) Arr::get($order, 'billing.email') : null,
            'order_created_at' => $this->parseDate(Arr::get($order, 'date_created_gmt') ?? Arr::get($order, 'date_created')),
            'order_paid_at' => $this->parseDate(Arr::get($order, 'date_paid_gmt') ?? Arr::get($order, 'date_paid')),
            'created_at' => $now,
            'updated_at' => $now,
        ];
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
