<?php

namespace App\Filament\Resources\WooShops;

use App\Filament\Resources\WooShops\Pages\CreateWooShop;
use App\Filament\Resources\WooShops\Pages\EditWooShop;
use App\Filament\Resources\WooShops\Pages\ListWooShops;
use App\Filament\Resources\WooShops\Schemas\WooShopForm;
use App\Filament\Resources\WooShops\Tables\WooShopsTable;
use App\Models\WooShop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WooShopResource extends Resource
{
    protected static ?string $model = WooShop::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'WooShop';

    protected static ?string $pluralModelLabel = 'WooShops';

    protected static string|UnitEnum|null $navigationGroup = 'Organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function form(Schema $schema): Schema
    {
        return WooShopForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WooShopsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWooShops::route('/'),
            'create' => CreateWooShop::route('/create'),
            'edit' => EditWooShop::route('/{record}/edit'),
        ];
    }
}
