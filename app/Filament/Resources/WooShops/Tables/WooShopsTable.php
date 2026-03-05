<?php

namespace App\Filament\Resources\WooShops\Tables;

use App\Models\WooShop;
use App\Services\WooCommerce\WooOrderSyncService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WooShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Shop name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('url')
                    ->label('Shop URL')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No WooShops found')
            ->emptyStateDescription('Create your first WooShop for this organization to start syncing data.')
            ->recordActions([
                Action::make('test_connection')
                    ->label('Test connection')
                    ->icon('heroicon-o-signal')
                    ->action(function (WooShop $record): void {
                        $result = app(WooOrderSyncService::class)->testConnection($record);

                        $notification = Notification::make()
                            ->title($result['success'] ? 'Connection successful' : 'Connection failed')
                            ->body($result['message']);

                        if ($result['success']) {
                            $notification->success();
                        } else {
                            $notification->danger();
                        }

                        $notification->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
