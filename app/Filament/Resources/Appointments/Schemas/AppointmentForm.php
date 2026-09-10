<?php

namespace App\Filament\Resources\Appointments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->relationship('patient', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn() => request()->query('patient_id')),
                Select::make('doctor_id')
                    ->relationship('doctor', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DateTimePicker::make('appointment_time')
                    ->seconds(false)
                    ->required(),
                ToggleButtons::make('visited')
                    ->options([
                        0 => 'Pending',
                        1 => 'Completed',
                    ])
                    ->colors([
                        0 => 'warning',
                        1 => 'success',
                    ])
                    ->icons([
                        0 => 'heroicon-o-clock',
                        1 => 'heroicon-o-check-circle',
                    ])
                    ->inline()
                    ->default(0)
                    ->required(),
            ]);
    }
}
