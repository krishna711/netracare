<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Filament\Resources\Patients\Actions\AbdmConsentAction;
use App\Filament\Resources\Patients\Actions\AbdmPatientAction;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Database\Eloquent\Builder;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('UHID')
                    ->sortable()
                    ->searchable(isIndividual: true),
                TextColumn::make('name')
                    ->searchable(isIndividual: true)
                    ->sortable(),
                TextColumn::make('abha_number')
                    ->label('ABHA ID')
                    ->formatStateUsing(fn ($state, Patient $record) => $record->formatted_abha_number ?: 'Not Linked')
                    ->badge()
                    ->color(fn ($state, Patient $record) => $record->isAbhaVerified() ? 'success' : 'gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mobile')
                    ->searchable(isIndividual: true),
                TextColumn::make('age')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sex')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('address')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Added Date')
                    ->dateTime('d-M-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Added From'),
                        DatePicker::make('created_until')->label('Added Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                SelectFilter::make('sex')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                        'Other' => 'Other',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                AbdmPatientAction::make(),
                AbdmConsentAction::make(),
                Action::make('addAppointment')
                    ->label('Appt')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->form([
                        TextInput::make('patient_name')
                            ->label('Patient Name')
                            ->default(fn(Patient $record) => $record->name)
                            ->disabled(),
                        Select::make('doctor_id')
                            ->label('Doctor')
                            ->options(Doctor::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        DateTimePicker::make('appointment_time')
                            ->label('Time')
                            ->required()
                            ->default(now()),
                    ])
                    ->action(function (Patient $record, array $data): void {
                        Appointment::create([
                            'patient_id' => $record->id,
                            'doctor_id' => $data['doctor_id'],
                            'appointment_time' => $data['appointment_time'],
                            'visited' => 0,
                        ]);
                    })
                    ->successNotificationTitle('Appointment scheduled successfully'),
                Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->url(fn(Patient $record): string => url("/print/patient/{$record->id}"))
                    ->openUrlInNewTab(),
                Action::make('history')
                    ->icon('heroicon-o-clock')
                    ->url(fn(Patient $record): string => url("/print/patient/history/{$record->id}"))
                    ->openUrlInNewTab(),
                Action::make('whatsapp')
                    ->label('WA')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn(Patient $record): string => "https://wa.me/+91{$record->mobile}?text=" . urlencode("Hello {$record->name}, Welcome to Netrika Netralaya Bhopal. Your registration has been confirmed."))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->label('Del'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
