<?php

namespace App\Filament\Resources\WooShops\Schemas;

use App\Models\WooShop;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                            ->helperText(fn (Get $get, ?string $operation): ?HtmlString => $operation === 'edit'
                                ? self::keysHelperText($get('url'))
                                : null)
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

    private static function keysHelperText(?string $shopUrl): HtmlString
    {
        $keysUrl = rtrim((string) $shopUrl, '/').'/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys';

        return new HtmlString('Get your API keys from <a href="'.e($keysUrl).'" target="_blank" rel="noopener noreferrer" class="underline">'.e($keysUrl).'</a>.');
    }
}
