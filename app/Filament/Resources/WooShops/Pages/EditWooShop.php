<?php

namespace App\Filament\Resources\WooShops\Pages;

use App\Filament\Resources\WooShops\WooShopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWooShop extends EditRecord
{
    protected static string $resource = WooShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
