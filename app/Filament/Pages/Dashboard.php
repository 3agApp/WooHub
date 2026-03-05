<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OrdersTableWidget;
use App\Filament\Widgets\OrganizationMembersWidget;
use App\Filament\Widgets\RevenueStatsWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Models\Organization;
use App\Services\Revenue\RevenueAnalyticsService;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static ?string $title = 'Revenue overview';

    protected static ?string $navigationLabel = 'Revenue overview';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return [
            RevenueStatsWidget::class,
            RevenueTrendChartWidget::class,
            OrdersTableWidget::class,
            OrganizationMembersWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema($this->getFilterSchema()),
        ];
    }

    /**
     * @return array<Component>
     */
    private function getFilterSchema(): array
    {
        return [
            Section::make('Filters')
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Start date')
                        ->default(now()->subDays(29)->toDateString()),
                    DatePicker::make('endDate')
                        ->label('End date')
                        ->default(now()->toDateString()),
                    Select::make('shopIds')
                        ->label('Shops')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->placeholder('All shops')
                        ->options(fn (): array => $this->getShopOptions()),
                    Select::make('aggregation')
                        ->label('Chart mode')
                        ->default('combined')
                        ->options([
                            'combined' => 'All selected shops combined',
                            'by_shop' => 'Break down by shop',
                        ]),
                    Select::make('trendGranularity')
                        ->label('Trend interval')
                        ->default('day')
                        ->options([
                            'day' => 'Daily',
                            'week' => 'Weekly',
                            'month' => 'Monthly',
                        ]),
                    Select::make('revenueStatuses')
                        ->label('Revenue statuses')
                        ->multiple()
                        ->default(RevenueAnalyticsService::DEFAULT_REVENUE_STATUSES)
                        ->options([
                            'pending' => 'Pending payment',
                            'processing' => 'Processing',
                            'on-hold' => 'On hold',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                            'failed' => 'Failed',
                            'trash' => 'Trash',
                        ]),
                ])
                ->columns(1),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getShopOptions(): array
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return [];
        }

        return $tenant->wooShops()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function getTenant(): ?Organization
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return null;
        }

        return $tenant;
    }
}
