<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\WooOrder;
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

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Orders';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getOrdersQuery())
            ->columns([
                TextColumn::make('wooShop.name')
                    ->label('Shop')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('external_order_id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('order_number')
                    ->label('Order no.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state, WooOrder $record): string => number_format((float) $state, 2).' '.($record->currency ?? '')),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order_created_at')
                    ->label('Created at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('order_created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * @return Builder<WooOrder>
     */
    private function getOrdersQuery(): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return WooOrder::query()->whereKey(-1);
        }

        return app(RevenueAnalyticsService::class)
            ->ordersQuery($tenant, $this->pageFilters)
            ->latest('order_created_at');
    }
}
