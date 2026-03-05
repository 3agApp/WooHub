<?php

namespace App\Filament\Resources\WooShops\Schemas;

use App\Models\WooShop;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WooShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('WooShop information')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Shop name')
                            ->required()
                            ->minLength(2)
                            ->maxLength(255)
                            ->placeholder('Enter WooShop name'),
                        TextInput::make('url')
                            ->label('Shop URL')
                            ->url()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('https://example.com'),
                        TextInput::make('consumer_key')
                            ->label('Consumer key')
                            ->required()
                            ->password()
                            ->revealable()
                            ->columnSpanFull(),
                        TextInput::make('consumer_secret')
                            ->label('Consumer secret')
                            ->required()
                            ->password()
                            ->revealable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Timestamps')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (?string $operation): bool => $operation === 'edit')
                    ->schema([
                        Placeholder::make('created_at_display')
                            ->label('Created at')
                            ->content(fn (?WooShop $record): string => $record?->created_at?->toDayDateTimeString() ?? '—'),
                        Placeholder::make('updated_at_display')
                            ->label('Updated at')
                            ->content(fn (?WooShop $record): string => $record?->updated_at?->toDayDateTimeString() ?? '—'),
                    ]),
            ]);
    }
}
