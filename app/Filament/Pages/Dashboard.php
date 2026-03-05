<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OrdersTableWidget;
use App\Filament\Widgets\OrganizationMembersWidget;
use App\Filament\Widgets\RevenueStatsWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Models\Organization;
use App\Models\WooShop;
use App\Services\Revenue\RevenueAnalyticsService;
use App\Services\WooCommerce\WooOrderSyncService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Throwable;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Revenue overview';

    protected static ?string $navigationLabel = 'Revenue overview';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
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
                            ->columnSpan([
                                'md' => 2,
                                'xl' => 3,
                            ])
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
                    ->columns([
                        'md' => 2,
                        'xl' => 3,
                    ]),
            ]);
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

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetchNow')
                ->label('Fetch data now')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(fn (): Notification => $this->fetchNow())
                ->disabled(fn (): bool => $this->getTenant()?->wooShops()->exists() !== true),
        ];
    }

    private function fetchNow(): Notification
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return Notification::make()
                ->title('No tenant selected')
                ->body('Select an organization before fetching data.')
                ->danger()
                ->send();
        }

        $analytics = app(RevenueAnalyticsService::class);
        $syncService = app(WooOrderSyncService::class);
        $shopIds = $analytics->resolveShopIds($tenant, $this->filters['shopIds'] ?? []);

        $shops = WooShop::query()
            ->where('organization_id', $tenant->getKey())
            ->whereIn('id', $shopIds)
            ->get();

        if ($shops->isEmpty()) {
            return Notification::make()
                ->title('No shops found')
                ->body('Create at least one WooShop or update your filters.')
                ->warning()
                ->send();
        }

        try {
            $result = $syncService->syncMany($shops);

            if ($result['failed_shops'] !== []) {
                return Notification::make()
                    ->title('Sync completed with issues')
                    ->body('Orders processed: '.$result['orders_count'].'. Failed shops: '.implode(', ', $result['failed_shops']))
                    ->warning()
                    ->send();
            }

            return Notification::make()
                ->title('Sync completed')
                ->body('Orders processed: '.$result['orders_count'].' from '.$result['shops_count'].' shop(s).')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            return Notification::make()
                ->title('Sync failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
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
