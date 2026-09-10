<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('patient_id')
                    ->label('Patient')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => 
                        \App\Models\Patient::where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($patient) => [$patient->id => "{$patient->name} ({$patient->id}) - {$patient->mobile}"])
                            ->toArray()
                    )
                    ->getOptionLabelUsing(function ($value): ?string {
                        $patient = \App\Models\Patient::find($value);
                        return $patient ? "{$patient->name} ({$patient->id}) - {$patient->mobile}" : null;
                    })
                    ->options(fn () => 
                        \App\Models\Patient::whereDate('created_at', today())
                            ->get()
                            ->mapWithKeys(fn ($patient) => [$patient->id => "{$patient->name} ({$patient->id}) - {$patient->mobile}"])
                            ->toArray()
                    )
                    ->required(),
                \Filament\Forms\Components\Select::make('description')
                    ->label('Description')
                    ->searchable()
                    ->options(function () {
                        $value = \App\Models\Setting::where('key', 'payment_type')->value('value') ?? 'Consultation';
                        $options = array_filter(array_map('trim', explode("\n", $value)));
                        return array_combine($options, $options);
                    })
                    ->default('Consultation'),
                \Filament\Forms\Components\Select::make('mode')
                    ->label('Mode')
                    ->searchable()
                    ->options(function () {
                        $value = \App\Models\Setting::where('key', 'payment_mode')->value('value') ?? 'Cash';
                        $options = array_filter(array_map('trim', explode("\n", $value)));
                        return array_combine($options, $options);
                    })
                    ->default('Cash'),
                \Filament\Forms\Components\TextInput::make('txn_id')
                    ->label('Txn ID/Cheque No')
                    ->default(null),
                \Filament\Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                \Filament\Forms\Components\Radio::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Complete' => 'Complete',
                    ])
                    ->default('Complete')
                    ->inline()
                    ->required(),
            ]);
    }
}
