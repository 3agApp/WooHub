<?php

namespace App\Services\Revenue;

use App\Models\Organization;
use App\Models\WooDailyRevenue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RevenueAnalyticsService
{
    /**
     * @var list<string>
     */
    public const ALLOWED_GRANULARITIES = ['day', 'week', 'month'];

    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{start:CarbonImmutable,end:CarbonImmutable,shop_ids:Collection<int, int>,aggregation:string,granularity:string}
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
     * @return Builder<WooDailyRevenue>
     */
    public function dailyRevenueQuery(Organization $organization, ?array $filters): Builder
    {
        $resolved = $this->resolveFilters($organization, $filters);

        return WooDailyRevenue::query()
            ->with('wooShop:id,name')
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereDate('revenue_date', '>=', $resolved['start']->toDateString())
            ->whereDate('revenue_date', '<=', $resolved['end']->toDateString());
    }

    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{current_revenue:float,previous_revenue:float,growth_rate:float|null,orders_count:int,currency:string}
     */
    public function overviewStats(Organization $organization, ?array $filters): array
    {
        $resolved = $this->resolveFilters($organization, $filters);
        $daysInPeriod = $resolved['start']->diffInDays($resolved['end']) + 1;

        $baseDailyRevenueQuery = $this->dailyRevenueQuery($organization, $filters);
        $currentRevenue = (float) (clone $baseDailyRevenueQuery)->sum('revenue_total');

        $previousEnd = $resolved['start']->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($daysInPeriod - 1)->startOfDay();

        $previousRevenue = (float) WooDailyRevenue::query()
            ->whereIn('woo_shop_id', $resolved['shop_ids'])
            ->whereDate('revenue_date', '>=', $previousStart->toDateString())
            ->whereDate('revenue_date', '<=', $previousEnd->toDateString())
            ->sum('revenue_total');

        $growthRate = $previousRevenue > 0
            ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100
            : null;

        $currency = (clone $baseDailyRevenueQuery)->whereNotNull('currency')->value('currency') ?? 'CHF';

        return [
            'current_revenue' => $currentRevenue,
            'previous_revenue' => $previousRevenue,
            'growth_rate' => $growthRate,
            'orders_count' => (int) (clone $baseDailyRevenueQuery)->sum('orders_count'),
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
            $resolved['granularity'],
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'All selected shops',
                    'data' => $this->mapBucketSeriesData($bucketStarts, $aggregatedRevenue, $resolved['granularity']),
                    'tension' => 0.25,
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.16)',
                ],
            ],
        ];
    }

    /**
     * @param  array{start:CarbonImmutable,end:CarbonImmutable,shop_ids:Collection<int, int>,aggregation:string,granularity:string}  $resolved
     * @param  list<CarbonImmutable>  $bucketStarts
     * @return list<array<string,mixed>>
     */
    private function datasetsByShop(Organization $organization, array $resolved, array $bucketStarts): array
    {
        $shops = $organization->wooShops()
            ->whereIn('id', $resolved['shop_ids'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return $shops->values()->map(function (mixed $shop, int $index) use ($resolved, $bucketStarts): array {
            $aggregatedRevenue = $this->aggregateRevenueByBuckets(
                collect([(int) $shop->id]),
                $resolved['start'],
                $resolved['end'],
                $resolved['granularity'],
            );

            return [
                'label' => (string) $shop->name,
                'data' => $this->mapBucketSeriesData($bucketStarts, $aggregatedRevenue, $resolved['granularity']),
                'tension' => 0.25,
                'borderColor' => $this->chartBorderColor($index),
                'backgroundColor' => $this->chartBackgroundColor($index),
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
     * @return Collection<string, float>
     */
    private function aggregateRevenueByBuckets(
        Collection $shopIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $granularity,
    ): Collection {
        $dailyRevenues = WooDailyRevenue::query()
            ->whereIn('woo_shop_id', $shopIds)
            ->whereDate('revenue_date', '>=', $start->toDateString())
            ->whereDate('revenue_date', '<=', $end->toDateString())
            ->get(['revenue_date', 'revenue_total']);

        $totals = [];

        foreach ($dailyRevenues as $dailyRevenue) {
            if (! $dailyRevenue->revenue_date) {
                continue;
            }

            $key = $this->bucketKey(CarbonImmutable::parse($dailyRevenue->revenue_date), $granularity);
            $totals[$key] = (float) ($totals[$key] ?? 0) + (float) $dailyRevenue->revenue_total;
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

    private function chartBorderColor(int $index): string
    {
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'];

        return $colors[$index % count($colors)];
    }

    private function chartBackgroundColor(int $index): string
    {
        $colors = [
            'rgba(59, 130, 246, 0.16)',
            'rgba(16, 185, 129, 0.16)',
            'rgba(245, 158, 11, 0.16)',
            'rgba(239, 68, 68, 0.16)',
            'rgba(139, 92, 246, 0.16)',
            'rgba(6, 182, 212, 0.16)',
            'rgba(132, 204, 22, 0.16)',
            'rgba(249, 115, 22, 0.16)',
        ];

        return $colors[$index % count($colors)];
    }
}
