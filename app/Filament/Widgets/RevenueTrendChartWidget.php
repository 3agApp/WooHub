<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Services\Revenue\RevenueAnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueTrendChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue trend';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        return app(RevenueAnalyticsService::class)->trendChart($tenant, $this->pageFilters);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
