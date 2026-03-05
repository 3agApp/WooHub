<?php

namespace App\Filament\Resources\WooShops\Pages;

use App\Filament\Resources\WooShops\WooShopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWooShops extends ListRecords
{
    protected static string $resource = WooShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
