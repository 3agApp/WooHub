<?php

namespace App\Filament\Resources\WooShops\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WooShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->url()
                    ->required()
                    ->maxLength(255),
                TextInput::make('currency')
                    ->required()
                    ->default('USD')
                    ->maxLength(3)
                    ->minLength(3)
                    ->formatStateUsing(fn (?string $state): string => str($state ?? '')->upper()->value())
                    ->dehydrateStateUsing(fn (?string $state): string => str($state ?? '')->upper()->value()),
                TextInput::make('consumer_key')
                    ->required()
                    ->password()
                    ->revealable()
                    ->columnSpanFull(),
                TextInput::make('consumer_secret')
                    ->required()
                    ->password()
                    ->revealable()
                    ->columnSpanFull(),
            ]);
    }
}
