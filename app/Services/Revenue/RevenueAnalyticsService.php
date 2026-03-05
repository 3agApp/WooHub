<?php

namespace App\Services\Revenue;

use App\Models\Organization;
use App\Models\WooOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAnalyticsService
{
    /**
     * @var list<string>
     */
    public const REVENUE_STATUSES = ['processing', 'completed', 'on-hold'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{start:CarbonImmutable,end:CarbonImmutable,shop_ids:Collection<int, int>,aggregation:string}
     */
    public function resolveFilters(Organization $organization, array $filters): array
    {
        $end = isset($filters['endDate']) && is_string($filters['endDate'])
            ? CarbonImmutable::parse($filters['endDate'])
            : now()->toImmutable();

        $start = isset($filters['startDate']) && is_string($filters['startDate'])
            ? CarbonImmutable::parse($filters['startDate'])
            : $end->subDays(29);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $aggregation = ($filters['aggregation'] ?? 'combined') === 'by_shop' ? 'by_shop' : 'combined';

        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
            'shop_ids' => $this->resolveShopIds($organization, $filters['shopIds'] ?? []),
            'aggregation' => $aggregation,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    public function resolveShopIds(Organization $organization, mixed $shopIds): Collection
    {
        $ids = is_array($shopIds)
            ? collect($shopIds)->map(fn (mixed $id): int => (int) $id)->filter()->values()
            : collect();

        $query = $organization->wooShops()->orderBy('name');

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<WooOrder>
     */
    public function ordersQuery(Organization $organization, array $filters): Builder
    {
        $resolved = $this->resolveFilters($organization, $filters);

        return WooOrder::query()
            ->with('wooShop:id,name')
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereBetween('order_created_at', [$resolved['start'], $resolved['end']]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{current_revenue:float,previous_revenue:float,growth_rate:float|null,orders_count:int,currency:string}
     */
    public function overviewStats(Organization $organization, array $filters): array
    {
        $resolved = $this->resolveFilters($organization, $filters);
        $daysInPeriod = $resolved['start']->diffInDays($resolved['end']) + 1;

        $baseOrdersQuery = $this->ordersQuery($organization, $filters);
        $currentRevenue = (float) (clone $baseOrdersQuery)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->sum('total');

        $previousEnd = $resolved['start']->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($daysInPeriod - 1)->startOfDay();

        $previousRevenue = (float) WooOrder::query()
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereBetween('order_created_at', [$previousStart, $previousEnd])
            ->whereIn('status', self::REVENUE_STATUSES)
            ->sum('total');

        $growthRate = $previousRevenue > 0
            ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100
            : null;

        $currency = (clone $baseOrdersQuery)->whereNotNull('currency')->value('currency') ?? 'CHF';

        return [
            'current_revenue' => $currentRevenue,
            'previous_revenue' => $previousRevenue,
            'growth_rate' => $growthRate,
            'orders_count' => (clone $baseOrdersQuery)->count(),
            'currency' => (string) $currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{labels:list<string>,datasets:list<array<string,mixed>>}
     */
    public function trendChart(Organization $organization, array $filters): array
    {
        $resolved = $this->resolveFilters($organization, $filters);
        $labels = $this->buildDateLabels($resolved['start'], $resolved['end']);

        if ($resolved['aggregation'] === 'by_shop') {
            return [
                'labels' => $labels,
                'datasets' => $this->datasetsByShop($organization, $resolved, $labels),
            ];
        }

        $dailyRevenue = WooOrder::query()
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereBetween('order_created_at', [$resolved['start'], $resolved['end']])
            ->whereIn('status', self::REVENUE_STATUSES)
            ->selectRaw('DATE(order_created_at) as order_date, SUM(total) as revenue_total')
            ->groupBy(DB::raw('DATE(order_created_at)'))
            ->pluck('revenue_total', 'order_date');

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'All selected shops',
                    'data' => $this->mapSeriesData($resolved['start'], $resolved['end'], $dailyRevenue),
                    'tension' => 0.25,
                ],
            ],
        ];
    }

    /**
     * @param  array{start:CarbonImmutable,end:CarbonImmutable,shop_ids:Collection<int, int>,aggregation:string}  $resolved
     * @param  list<string>  $labels
     * @return list<array<string,mixed>>
     */
    private function datasetsByShop(Organization $organization, array $resolved, array $labels): array
    {
        $shops = $organization->wooShops()
            ->whereIn('id', $resolved['shop_ids'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return $shops->map(function (mixed $shop) use ($resolved): array {
            $dailyRevenue = WooOrder::query()
                ->where('woo_shop_id', $shop->id)
                ->whereBetween('order_created_at', [$resolved['start'], $resolved['end']])
                ->whereIn('status', self::REVENUE_STATUSES)
                ->selectRaw('DATE(order_created_at) as order_date, SUM(total) as revenue_total')
                ->groupBy(DB::raw('DATE(order_created_at)'))
                ->pluck('revenue_total', 'order_date');

            return [
                'label' => (string) $shop->name,
                'data' => $this->mapSeriesData($resolved['start'], $resolved['end'], $dailyRevenue),
                'tension' => 0.25,
            ];
        })->values()->all();
    }

    /**
     * @return list<string>
     */
    private function buildDateLabels(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $labels = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $labels[] = $cursor->format('d M');
            $cursor = $cursor->addDay();
        }

        return $labels;
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, mixed>  $dailyRevenue
     * @return list<float>
     */
    private function mapSeriesData(CarbonImmutable $start, CarbonImmutable $end, Collection $dailyRevenue): array
    {
        $points = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $points[] = (float) ($dailyRevenue[$key] ?? 0);
            $cursor = $cursor->addDay();
        }

        return $points;
    }
}
