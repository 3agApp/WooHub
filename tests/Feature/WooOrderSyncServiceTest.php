<?php

use App\Models\Organization;
use App\Models\WooDailyRevenue;
use App\Models\WooShop;
use App\Services\WooCommerce\WooRevenueSyncService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('syncs woocommerce orders into daily revenue aggregates', function () {
    $shop = WooShop::factory()->for(Organization::factory())->create([
        'url' => 'https://toysonline.ch',
        'consumer_key' => 'ck_test_key',
        'consumer_secret' => 'cs_test_secret',
    ]);

    Http::fake([
        'https://toysonline.ch/wp-json/wc/v3/orders*' => Http::sequence()
            ->push([
                [
                    'id' => 101,
                    'number' => '101',
                    'status' => 'completed',
                    'currency' => 'CHF',
                    'total' => '120.50',
                    'date_created_gmt' => '2026-03-01T09:00:00',
                    'date_paid_gmt' => '2026-03-01T09:15:00',
                    'billing' => [
                        'first_name' => 'Jane',
                        'last_name' => 'Doe',
                        'email' => 'jane@example.com',
                    ],
                ],
                [
                    'id' => 103,
                    'number' => '103',
                    'status' => 'pending',
                    'currency' => 'CHF',
                    'total' => '999.00',
                    'date_created_gmt' => '2026-03-01T12:00:00',
                ],
            ], 200, ['X-WP-TotalPages' => 2])
            ->push([
                [
                    'id' => 102,
                    'number' => '102',
                    'status' => 'processing',
                    'currency' => 'CHF',
                    'total' => '87.00',
                    'date_created_gmt' => '2026-03-02T10:00:00',
                    'date_paid_gmt' => '2026-03-02T10:05:00',
                    'billing' => [
                        'first_name' => 'John',
                        'last_name' => 'Smith',
                        'email' => 'john@example.com',
                    ],
                ],
            ], 200, ['X-WP-TotalPages' => 2]),
    ]);

    $processed = app(WooRevenueSyncService::class)->syncShop($shop);

    expect($processed)->toBe(2)
        ->and(WooDailyRevenue::query()->count())->toBe(2)
        ->and(WooDailyRevenue::query()->sum('orders_count'))->toBe(2)
        ->and((float) WooDailyRevenue::query()->sum('revenue_total'))->toBe(207.5);

    $shop->refresh();

    expect($shop->last_sync_status)->toBe('success')
        ->and($shop->last_synced_at)->not->toBeNull()
        ->and($shop->last_sync_error)->toBeNull();
});

it('marks a shop as failed when the woocommerce request fails', function () {
    $shop = WooShop::factory()->for(Organization::factory())->create([
        'url' => 'https://toysonline.ch',
    ]);

    Http::fake([
        'https://toysonline.ch/wp-json/wc/v3/orders*' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    expect(fn () => app(WooRevenueSyncService::class)->syncShop($shop))
        ->toThrow(RequestException::class);

    $shop->refresh();

    expect($shop->last_sync_status)->toBe('failed')
        ->and($shop->last_synced_at)->not->toBeNull()
        ->and($shop->last_sync_error)->toContain('401');
});

it('can test shop connection successfully', function () {
    $shop = WooShop::factory()->for(Organization::factory())->create([
        'url' => 'https://toysonline.ch',
        'consumer_key' => 'ck_test_key',
        'consumer_secret' => 'cs_test_secret',
    ]);

    Http::fake([
        'https://toysonline.ch/wp-json/wc/v3/orders*' => Http::response([], 200, ['X-WP-Total' => 42]),
    ]);

    $result = app(WooRevenueSyncService::class)->testConnection($shop);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('Connection successful');
});

it('reports connection test failure', function () {
    $shop = WooShop::factory()->for(Organization::factory())->create([
        'url' => 'https://toysonline.ch',
    ]);

    Http::fake([
        'https://toysonline.ch/wp-json/wc/v3/orders*' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $result = app(WooRevenueSyncService::class)->testConnection($shop);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('401');
});
