<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\WooDailyRevenue;
use App\Services\Revenue\RevenueAnalyticsService;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OrdersTableWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Daily revenue';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getOrdersQuery())
            ->columns([
                TextColumn::make('wooShop.name')
                    ->label('Shop')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('revenue_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->sortable(),
                TextColumn::make('revenue_total')
                    ->label('Revenue')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state, WooDailyRevenue $record): string => number_format((float) $state, 2).' '.($record->currency ?? 'CHF')),
                TextColumn::make('currency')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('revenue_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * @return Builder<WooDailyRevenue>
     */
    private function getOrdersQuery(): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return WooDailyRevenue::query()->whereKey(-1);
        }

        return app(RevenueAnalyticsService::class)
            ->dailyRevenueQuery($tenant, $this->pageFilters)
            ->latest('revenue_date');
    }
}
