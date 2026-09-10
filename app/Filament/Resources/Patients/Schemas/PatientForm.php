<?php

namespace App\Filament\Resources\Patients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('mobile')
                    ->required()
                    ->maxLength(255),
                TextInput::make('age')
                    ->numeric()
                    ->required(),
                Select::make('sex')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                        'Other' => 'Other',
                    ])
                    ->required(),
                TextInput::make('occuption')
                    ->label('Occupation')
                    ->maxLength(255),
                Textarea::make('address')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
