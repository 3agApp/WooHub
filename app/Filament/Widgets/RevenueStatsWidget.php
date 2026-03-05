<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Services\Revenue\RevenueAnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [];
        }

        $stats = app(RevenueAnalyticsService::class)->overviewStats($tenant, $this->pageFilters);
        $growthRate = $stats['growth_rate'];

        $growthText = $growthRate === null
            ? 'Not enough previous data'
            : number_format($growthRate, 2).'% vs previous period';

        return [
            Stat::make('Revenue', number_format($stats['current_revenue'], 2).' '.$stats['currency'])
                ->description('Previous period: '.number_format($stats['previous_revenue'], 2).' '.$stats['currency']),
            Stat::make('Growth', $growthRate === null ? '—' : number_format($growthRate, 2).'%')
                ->description($growthText)
                ->color($growthRate !== null && $growthRate < 0 ? 'danger' : 'success'),
            Stat::make('Orders', number_format($stats['orders_count'])),
        ];
    }
}
