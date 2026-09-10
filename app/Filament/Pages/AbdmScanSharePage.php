<?php

namespace App\Filament\Pages;

use App\Models\AbdmScanShare;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\Abdm\ScanAndShareService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbdmScanSharePage extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationLabel(): string
    {
        return 'Scan & Share (Counter)';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ABDM / Ayushman Bharat';
    }

    protected static ?int $navigationSort = 110;

    protected string $view = 'filament.pages.abdm-scan-share-page';

    public array $qrPayload = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function mount(ScanAndShareService $service): void
    {
        $this->qrPayload = $service->generateCounterQrPayload();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AbdmScanShare::query()->latest()
            )
            ->columns([
                TextColumn::make('token_number')
                    ->label('Token #')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Patient Name')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('abha_number')
                    ->label('ABHA Number')
                    ->searchable(),
                TextColumn::make('abha_address')
                    ->label('ABHA Address')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('gender')
                    ->label('Sex')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'M' => 'Male',
                        'F' => 'Female',
                        default => $state ?? '-'
                    }),
                TextColumn::make('dob')
                    ->label('DOB')
                    ->toggleable(),
                TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'registered' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Scan Time')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('registerPatient')
                    ->label('Register & Book OPD')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (AbdmScanShare $record) => $record->status === 'pending')
                    ->form([
                        TextInput::make('name')
                            ->label('Patient Name')
                            ->default(fn (AbdmScanShare $record) => $record->name)
                            ->required(),
                        TextInput::make('mobile')
                            ->label('Mobile Number')
                            ->default(fn (AbdmScanShare $record) => $record->mobile)
                            ->required(),
                        Select::make('doctor_id')
                            ->label('Select Consulting Doctor')
                            ->options(Doctor::all()->pluck('name', 'id'))
                            ->required(),
                        DateTimePicker::make('appointment_time')
                            ->label('Appointment Time')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (AbdmScanShare $record, array $data): void {
                        // Calculate age from DOB if present
                        $age = 30;
                        if (!empty($record->dob)) {
                            try {
                                $age = \Carbon\Carbon::parse($record->dob)->age;
                            } catch (\Throwable $e) {
                                // Default
                            }
                        }

                        // Map gender
                        $sex = match (strtoupper($record->gender ?? '')) {
                            'M', 'MALE' => 'Male',
                            'F', 'FEMALE' => 'Female',
                            default => 'Other',
                        };

                        // Create or match patient
                        $patient = Patient::firstOrCreate(
                            ['mobile' => $data['mobile']],
                            [
                                'name' => $data['name'],
                                'email' => null,
                                'age' => $age,
                                'sex' => $sex,
                                'address' => $record->address,
                                'occuption' => null,
                                'abha_number' => $record->abha_number,
                                'abha_address' => $record->abha_address,
                                'abdm_status' => 'verified',
                                'abdm_profile' => $record->raw_profile,
                                'abdm_verified_at' => now(),
                            ]
                        );

                        // If patient already existed, update their ABHA details
                        if (!$patient->wasRecentlyCreated) {
                            $patient->update([
                                'abha_number' => $record->abha_number ?: $patient->abha_number,
                                'abha_address' => $record->abha_address ?: $patient->abha_address,
                                'abdm_status' => 'verified',
                                'abdm_verified_at' => now(),
                            ]);
                        }

                        // Create Appointment
                        $appointment = Appointment::create([
                            'patient_id' => $patient->id,
                            'doctor_id' => $data['doctor_id'],
                            'appointment_time' => $data['appointment_time'],
                            'visited' => 0,
                        ]);

                        // Update queue record status
                        $record->update([
                            'status' => 'registered',
                            'patient_id' => $patient->id,
                            'appointment_id' => $appointment->id,
                        ]);

                        Notification::make()
                            ->title("Patient Registered (UHID: {$patient->id})")
                            ->body("OPD Appointment scheduled with Token #{$record->token_number}")
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (AbdmScanShare $record) => $record->status === 'pending')
                    ->action(fn (AbdmScanShare $record) => $record->update(['status' => 'cancelled'])),
            ]);
    }
}
