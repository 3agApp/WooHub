<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Organization;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register organization';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    protected function handleRegistration(array $data): Organization
    {
        $organization = Organization::create([
            'name' => $data['name'],
        ]);

        $organization->users()->attach(auth()->id());

        return $organization;
    }
}
