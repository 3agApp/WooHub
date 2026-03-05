<?php

use App\Models\Organization;
use App\Models\WooDailyRevenue;
use App\Models\WooShop;
use App\Services\Revenue\RevenueAnalyticsService;
use Illuminate\Support\Carbon;

it('calculates current revenue, previous period revenue, and growth', function () {
    Carbon::setTestNow('2026-03-05 12:00:00');

    $organization = Organization::factory()->create();
    $shop = WooShop::factory()->for($organization)->create();

    WooDailyRevenue::factory()->for($shop, 'wooShop')->create([
        'revenue_date' => '2026-03-04',
        'revenue_total' => 370,
        'orders_count' => 4,
        'currency' => 'CHF',
    ]);

    WooDailyRevenue::factory()->for($shop, 'wooShop')->create([
        'revenue_date' => '2026-03-03',
        'revenue_total' => 300,
        'orders_count' => 3,
        'currency' => 'CHF',
    ]);

    WooDailyRevenue::factory()->for($shop, 'wooShop')->create([
        'revenue_date' => '2026-03-01',
        'revenue_total' => 60,
        'orders_count' => 1,
        'currency' => 'CHF',
    ]);

    $stats = app(RevenueAnalyticsService::class)->overviewStats($organization, [
        'startDate' => '2026-03-03',
        'endDate' => '2026-03-04',
        'shopIds' => [$shop->getKey()],
        'aggregation' => 'combined',
    ]);

    expect($stats['current_revenue'])->toBe(670.0)
        ->and($stats['previous_revenue'])->toBe(60.0)
        ->and(round($stats['growth_rate'] ?? 0, 2))->toBe(1016.67)
        ->and($stats['orders_count'])->toBe(7)
        ->and($stats['currency'])->toBe('CHF');

    Carbon::setTestNow();
});

it('builds chart datasets for combined and per-shop modes', function () {
    $organization = Organization::factory()->create();
    $shopA = WooShop::factory()->for($organization)->create(['name' => 'Shop A']);
    $shopB = WooShop::factory()->for($organization)->create(['name' => 'Shop B']);

    WooDailyRevenue::factory()->for($shopA, 'wooShop')->create([
        'revenue_date' => '2026-03-04',
        'revenue_total' => 100,
        'orders_count' => 1,
    ]);

    WooDailyRevenue::factory()->for($shopB, 'wooShop')->create([
        'revenue_date' => '2026-03-04',
        'revenue_total' => 200,
        'orders_count' => 1,
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

it('supports monthly trend interval', function () {
    $organization = Organization::factory()->create();
    $shop = WooShop::factory()->for($organization)->create();

    WooDailyRevenue::factory()->for($shop, 'wooShop')->create([
        'revenue_date' => '2026-01-10',
        'revenue_total' => 80,
        'orders_count' => 1,
    ]);

    WooDailyRevenue::factory()->for($shop, 'wooShop')->create([
        'revenue_date' => '2026-02-18',
        'revenue_total' => 120,
        'orders_count' => 1,
    ]);

    WooDailyRevenue::factory()->for($shop, 'wooShop')->create([
        'revenue_date' => '2026-03-02',
        'revenue_total' => 500,
        'orders_count' => 1,
    ]);

    $service = app(RevenueAnalyticsService::class);

    $stats = $service->overviewStats($organization, [
        'startDate' => '2026-01-01',
        'endDate' => '2026-03-31',
        'shopIds' => [$shop->id],
    ]);

    expect($stats['current_revenue'])->toBe(700.0);

    $trend = $service->trendChart($organization, [
        'startDate' => '2026-01-01',
        'endDate' => '2026-03-31',
        'shopIds' => [$shop->id],
        'aggregation' => 'combined',
        'trendGranularity' => 'month',
    ]);

    expect($trend['labels'])->toHaveCount(3)
        ->and($trend['datasets'][0]['data'][0])->toBe(80.0)
        ->and($trend['datasets'][0]['data'][1])->toBe(120.0)
        ->and($trend['datasets'][0]['data'][2])->toBe(500.0);
});
