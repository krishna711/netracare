<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Get;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                Select::make('role')
                    ->options(function () {
                        $options = [
                            'admin' => 'Admin',
                            'manager' => 'Manager',
                        ];
                        if (auth()->user() && auth()->user()->isSuperAdmin()) {
                            $options['super_admin'] = 'Super Admin';
                        }
                        return $options;
                    })
                    ->required()
                    ->live(),
                CheckboxList::make('permissions')
                    ->options([
                        'view_dashboard_cards' => 'View Dashboard Cards',
                        'view_dashboard_followups' => 'View Dashboard Followups',
                        'manage_patients' => 'Manage Patients',
                        'manage_appointments' => 'Manage Appointments',
                        'manage_payments' => 'Manage Payments',
                        'manage_doctors' => 'Manage Doctors',
                        'manage_reports' => 'Manage Reports',
                        'manage_settings' => 'Manage Settings',
                        'manage_backups' => 'Manage Backups',
                    ])
                    ->visible(fn ($get) => $get('role') === 'manager')
                    ->columns(2),
            ]);
    }
}
