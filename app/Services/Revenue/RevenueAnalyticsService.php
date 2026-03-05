<?php

namespace App\Services\Revenue;

use App\Models\Organization;
use App\Models\WooOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RevenueAnalyticsService
{
    /**
     * @var list<string>
     */
    public const DEFAULT_REVENUE_STATUSES = ['processing', 'completed'];

    /**
     * @var list<string>
     */
    public const ALLOWED_REVENUE_STATUSES = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash'];

    /**
     * @var list<string>
     */
    public const ALLOWED_GRANULARITIES = ['day', 'week', 'month'];

    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{start:CarbonImmutable,end:CarbonImmutable,shop_ids:Collection<int, int>,aggregation:string,granularity:string,revenue_statuses:list<string>}
     */
    public function resolveFilters(Organization $organization, ?array $filters): array
    {
        $filters ??= [];

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
            'granularity' => $this->resolveGranularity($filters['trendGranularity'] ?? null),
            'revenue_statuses' => $this->resolveRevenueStatuses($filters['revenueStatuses'] ?? null),
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
     * @param  array<string, mixed> | null  $filters
     * @return Builder<WooOrder>
     */
    public function ordersQuery(Organization $organization, ?array $filters): Builder
    {
        $resolved = $this->resolveFilters($organization, $filters);

        return WooOrder::query()
            ->with('wooShop:id,name')
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereBetween('order_created_at', [$resolved['start'], $resolved['end']]);
    }

    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{current_revenue:float,previous_revenue:float,growth_rate:float|null,orders_count:int,currency:string}
     */
    public function overviewStats(Organization $organization, ?array $filters): array
    {
        $resolved = $this->resolveFilters($organization, $filters);
        $daysInPeriod = $resolved['start']->diffInDays($resolved['end']) + 1;

        $baseOrdersQuery = $this->ordersQuery($organization, $filters);
        $currentRevenue = (float) (clone $baseOrdersQuery)
            ->whereIn('status', $resolved['revenue_statuses'])
            ->sum('total');

        $previousEnd = $resolved['start']->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($daysInPeriod - 1)->startOfDay();

        $previousRevenue = (float) WooOrder::query()
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereBetween('order_created_at', [$previousStart, $previousEnd])
            ->whereIn('status', $resolved['revenue_statuses'])
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
     * @param  array<string, mixed> | null  $filters
     * @return array{labels:list<string>,datasets:list<array<string,mixed>>}
     */
    public function trendChart(Organization $organization, ?array $filters): array
    {
        $resolved = $this->resolveFilters($organization, $filters);
        $bucketStarts = $this->buildBucketStarts($resolved['start'], $resolved['end'], $resolved['granularity']);
        $labels = $this->buildBucketLabels($bucketStarts, $resolved['granularity']);

        if ($resolved['aggregation'] === 'by_shop') {
            return [
                'labels' => $labels,
                'datasets' => $this->datasetsByShop($organization, $resolved, $bucketStarts),
            ];
        }

        $aggregatedRevenue = $this->aggregateRevenueByBuckets(
            $resolved['shop_ids'],
            $resolved['start'],
            $resolved['end'],
            $resolved['revenue_statuses'],
            $resolved['granularity'],
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'All selected shops',
                    'data' => $this->mapBucketSeriesData($bucketStarts, $aggregatedRevenue, $resolved['granularity']),
                    'tension' => 0.25,
                ],
            ],
        ];
    }

    /**
     * @param  array{start:CarbonImmutable,end:CarbonImmutable,shop_ids:Collection<int, int>,aggregation:string,granularity:string,revenue_statuses:list<string>}  $resolved
     * @param  list<CarbonImmutable>  $bucketStarts
     * @return list<array<string,mixed>>
     */
    private function datasetsByShop(Organization $organization, array $resolved, array $bucketStarts): array
    {
        $shops = $organization->wooShops()
            ->whereIn('id', $resolved['shop_ids'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return $shops->map(function (mixed $shop) use ($resolved, $bucketStarts): array {
            $aggregatedRevenue = $this->aggregateRevenueByBuckets(
                collect([(int) $shop->id]),
                $resolved['start'],
                $resolved['end'],
                $resolved['revenue_statuses'],
                $resolved['granularity'],
            );

            return [
                'label' => (string) $shop->name,
                'data' => $this->mapBucketSeriesData($bucketStarts, $aggregatedRevenue, $resolved['granularity']),
                'tension' => 0.25,
            ];
        })->values()->all();
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function buildBucketStarts(CarbonImmutable $start, CarbonImmutable $end, string $granularity): array
    {
        $buckets = [];
        $cursor = $this->normalizeBucketStart($start, $granularity);

        while ($cursor->lessThanOrEqualTo($end)) {
            $buckets[] = $cursor;
            $cursor = $this->nextBucket($cursor, $granularity);
        }

        return $buckets;
    }

    /**
     * @param  list<CarbonImmutable>  $bucketStarts
     * @return list<string>
     */
    private function buildBucketLabels(array $bucketStarts, string $granularity): array
    {
        return collect($bucketStarts)
            ->map(fn (CarbonImmutable $bucketStart): string => $this->formatBucketLabel($bucketStart, $granularity))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $shopIds
     * @param  list<string>  $revenueStatuses
     * @return Collection<string, float>
     */
    private function aggregateRevenueByBuckets(
        Collection $shopIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $revenueStatuses,
        string $granularity,
    ): Collection {
        $orders = WooOrder::query()
            ->whereIn('woo_shop_id', $shopIds)
            ->whereBetween('order_created_at', [$start, $end])
            ->whereIn('status', $revenueStatuses)
            ->get(['order_created_at', 'total']);

        $totals = [];

        foreach ($orders as $order) {
            if (! $order->order_created_at) {
                continue;
            }

            $key = $this->bucketKey(CarbonImmutable::instance($order->order_created_at), $granularity);
            $totals[$key] = (float) ($totals[$key] ?? 0) + (float) $order->total;
        }

        return collect($totals);
    }

    /**
     * @param  list<CarbonImmutable>  $bucketStarts
     * @param  Collection<string, float>  $aggregatedRevenue
     * @return list<float>
     */
    private function mapBucketSeriesData(array $bucketStarts, Collection $aggregatedRevenue, string $granularity): array
    {
        return collect($bucketStarts)
            ->map(fn (CarbonImmutable $bucketStart): float => (float) ($aggregatedRevenue->get($this->bucketKey($bucketStart, $granularity)) ?? 0))
            ->values()
            ->all();
    }

    private function resolveGranularity(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::ALLOWED_GRANULARITIES, true)) {
            return $value;
        }

        return 'day';
    }

    /**
     * @return list<string>
     */
    private function resolveRevenueStatuses(mixed $statuses): array
    {
        $selected = is_array($statuses)
            ? collect($statuses)
                ->filter(fn (mixed $status): bool => is_string($status))
                ->filter(fn (string $status): bool => in_array($status, self::ALLOWED_REVENUE_STATUSES, true))
                ->values()
                ->all()
            : [];

        if ($selected === []) {
            return self::DEFAULT_REVENUE_STATUSES;
        }

        return array_values(array_unique($selected));
    }

    private function normalizeBucketStart(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'month' => $date->startOfMonth(),
            'week' => $date->startOfWeek(),
            default => $date->startOfDay(),
        };
    }

    private function nextBucket(CarbonImmutable $bucketStart, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'month' => $bucketStart->addMonth(),
            'week' => $bucketStart->addWeek(),
            default => $bucketStart->addDay(),
        };
    }

    private function bucketKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'month' => $date->format('Y-m'),
            'week' => $date->format('o-\\WW'),
            default => $date->format('Y-m-d'),
        };
    }

    private function formatBucketLabel(CarbonImmutable $bucketStart, string $granularity): string
    {
        return match ($granularity) {
            'month' => $bucketStart->format('M Y'),
            'week' => 'W'.$bucketStart->isoWeek().' '.$bucketStart->isoWeekYear(),
            default => $bucketStart->format('d M'),
        };
    }
}
