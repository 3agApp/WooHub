<?php

use App\Models\Organization;
use App\Models\WooOrder;
use App\Models\WooShop;
use App\Services\Revenue\RevenueAnalyticsService;
use Illuminate\Support\Carbon;

it('calculates current revenue, previous period revenue, and growth', function () {
    Carbon::setTestNow('2026-03-05 12:00:00');

    $organization = Organization::factory()->create();
    $shop = WooShop::factory()->for($organization)->create();

    WooOrder::factory()->for($shop, 'wooShop')->create([
        'status' => 'completed',
        'total' => 100,
        'currency' => 'CHF',
        'order_created_at' => '2026-03-04 10:00:00',
    ]);

    WooOrder::factory()->for($shop, 'wooShop')->create([
        'status' => 'processing',
        'total' => 50,
        'currency' => 'CHF',
        'order_created_at' => '2026-03-03 10:00:00',
    ]);

    WooOrder::factory()->for($shop, 'wooShop')->create([
        'status' => 'pending',
        'total' => 300,
        'currency' => 'CHF',
        'order_created_at' => '2026-03-03 09:00:00',
    ]);

    WooOrder::factory()->for($shop, 'wooShop')->create([
        'status' => 'completed',
        'total' => 60,
        'currency' => 'CHF',
        'order_created_at' => '2026-03-01 10:00:00',
    ]);

    $stats = app(RevenueAnalyticsService::class)->overviewStats($organization, [
        'startDate' => '2026-03-03',
        'endDate' => '2026-03-04',
        'shopIds' => [$shop->getKey()],
        'aggregation' => 'combined',
    ]);

    expect($stats['current_revenue'])->toBe(150.0)
        ->and($stats['previous_revenue'])->toBe(60.0)
        ->and(round($stats['growth_rate'] ?? 0, 2))->toBe(150.0)
        ->and($stats['orders_count'])->toBe(3)
        ->and($stats['currency'])->toBe('CHF');

    Carbon::setTestNow();
});

it('builds chart datasets for combined and per-shop modes', function () {
    $organization = Organization::factory()->create();
    $shopA = WooShop::factory()->for($organization)->create(['name' => 'Shop A']);
    $shopB = WooShop::factory()->for($organization)->create(['name' => 'Shop B']);

    WooOrder::factory()->for($shopA, 'wooShop')->create([
        'status' => 'completed',
        'total' => 100,
        'order_created_at' => '2026-03-04 10:00:00',
    ]);

    WooOrder::factory()->for($shopB, 'wooShop')->create([
        'status' => 'completed',
        'total' => 200,
        'order_created_at' => '2026-03-04 11:00:00',
    ]);

    $service = app(RevenueAnalyticsService::class);

    $combined = $service->trendChart($organization, [
        'startDate' => '2026-03-03',
        'endDate' => '2026-03-04',
        'aggregation' => 'combined',
        'shopIds' => [$shopA->id, $shopB->id],
    ]);

    expect($combined['labels'])->toHaveCount(2)
        ->and($combined['datasets'])->toHaveCount(1)
        ->and($combined['datasets'][0]['data'][0])->toBe(0.0)
        ->and($combined['datasets'][0]['data'][1])->toBe(300.0);

    $byShop = $service->trendChart($organization, [
        'startDate' => '2026-03-03',
        'endDate' => '2026-03-04',
        'aggregation' => 'by_shop',
        'shopIds' => [$shopA->id, $shopB->id],
    ]);

    expect($byShop['datasets'])->toHaveCount(2)
        ->and($byShop['datasets'][0]['data'][1])->toBe(100.0)
        ->and($byShop['datasets'][1]['data'][1])->toBe(200.0);

    Carbon::setTestNow();
});
