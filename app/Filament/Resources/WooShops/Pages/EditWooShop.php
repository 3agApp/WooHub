<?php

namespace App\Filament\Resources\WooShops\Pages;

use App\Filament\Resources\WooShops\WooShopResource;
use App\Models\WooShop;
use App\Services\WooCommerce\WooRevenueSyncService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWooShop extends EditRecord
{
    protected static string $resource = WooShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test connection')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    /** @var WooShop $record */
                    $record = $this->record;
                    $result = app(WooRevenueSyncService::class)->testConnection($record);

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
            DeleteAction::make(),
        ];
    }
}
