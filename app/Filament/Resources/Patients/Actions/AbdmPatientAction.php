<?php

namespace App\Filament\Resources\Patients\Actions;

use App\Models\Patient;
use App\Services\Abdm\AbhaEnrollmentService;
use App\Services\Abdm\AbhaVerifyService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AbdmPatientAction
{
    public static function make(): Action
    {
        return Action::make('abdmServices')
            ->label('ABHA')
            ->icon('heroicon-o-identification')
            ->color(fn (Patient $record): string => $record->isAbhaVerified() ? 'success' : 'gray')
            ->tooltip(fn (Patient $record): string => $record->isAbhaVerified() ? "ABHA: {$record->formatted_abha_number} ({$record->abha_address})" : 'Click to Create or Verify ABHA')
            ->modalHeading(fn (Patient $record): string => "ABDM / ABHA Identity - {$record->name} (UHID: {$record->id})")
            ->modalWidth('3xl')
            ->modalSubmitActionLabel(fn (Patient $record): string => $record->isAbhaVerified() ? 'Close' : 'Complete & Save')
            ->form(function (Patient $record): array {
                if ($record->isAbhaVerified()) {
                    return [
                        Section::make('Verified ABHA Identity')
                            ->schema([
                                Placeholder::make('abha_card_preview')
                                    ->label('')
                                    ->content(new HtmlString("
                                        <div class='p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 flex items-center justify-between'>
                                            <div class='flex items-center gap-4'>
                                                <div class='w-12 h-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold text-xl'>
                                                    ✓
                                                </div>
                                                <div>
                                                    <h4 class='font-bold text-emerald-950 dark:text-emerald-200 text-base'>ABHA Linked & Active</h4>
                                                    <p class='text-sm text-emerald-800 dark:text-emerald-400 font-mono'>Number: {$record->formatted_abha_number}</p>
                                                    <p class='text-xs text-emerald-700 dark:text-emerald-400'>Address: {$record->abha_address}</p>
                                                </div>
                                            </div>
                                            <div>
                                                <a href='/print/patient/abha-card/{$record->id}' target='_blank' class='inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow transition'>
                                                    Print Official Card
                                                </a>
                                            </div>
                                        </div>
                                    ")),
                            ]),
                    ];
                }

                // Unverified Flow
                return [
                    Radio::make('flow_type')
                        ->label('Select ABHA Operation')
                        ->options([
                            'create' => '1. Create New ABHA (via Aadhaar OTP)',
                            'verify' => '2. Verify & Link Existing ABHA (via Mobile/ABHA OTP)',
                        ])
                        ->default('create')
                        ->live(),

                    // Section for ABHA Creation
                    Section::make('Create New ABHA via Aadhaar')
                        ->visible(fn ($get) => $get('flow_type') === 'create')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('aadhaar_number')
                                    ->label('Patient Aadhaar Number')
                                    ->placeholder('12-digit Aadhaar')
                                    ->maxLength(12)
                                    ->suffixAction(
                                        Action::make('sendAadhaarOtp')
                                            ->label('Send OTP')
                                            ->icon('heroicon-o-paper-airplane')
                                            ->action(function ($set, $get, AbhaEnrollmentService $enrolService) {
                                                $aadhaar = $get('aadhaar_number');
                                                if (empty($aadhaar)) {
                                                    Notification::make()->title('Please enter 12-digit Aadhaar number')->warning()->send();
                                                    return;
                                                }

                                                try {
                                                    $res = $enrolService->requestAadhaarOtp($aadhaar);
                                                    $set('txn_id', $res['txnId']);
                                                    Notification::make()
                                                        ->title('Aadhaar OTP Sent!')
                                                        ->body('OTP has been sent to the mobile linked with Aadhaar.')
                                                        ->success()
                                                        ->send();
                                                } catch (\Throwable $e) {
                                                    Notification::make()
                                                        ->title('Failed to Send OTP')
                                                        ->body($e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            })
                                    ),
                                TextInput::make('comm_mobile')
                                    ->label('Mobile for Communication')
                                    ->default($record->mobile)
                                    ->required(),
                            ]),

                            TextInput::make('txn_id')
                                ->label('ABDM Transaction ID (Auto-filled on OTP send)')
                                ->disabled()
                                ->dehydrated(),

                            Grid::make(2)->schema([
                                TextInput::make('otp_value')
                                    ->label('Enter 6-Digit OTP')
                                    ->placeholder('e.g. 123456')
                                    ->password()
                                    ->revealable(),
                                TextInput::make('preferred_abha_address')
                                    ->label('Preferred ABHA Address (Optional)')
                                    ->placeholder('e.g. rahul.sharma@abdm')
                                    ->helperText('Defaults to system assigned handle if blank.'),
                            ]),
                        ]),

                    // Section for ABHA Verification
                    Section::make('Verify Existing ABHA')
                        ->visible(fn ($get) => $get('flow_type') === 'verify')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('search_identifier')
                                    ->label('ABHA Number or Mobile Number')
                                    ->default($record->mobile)
                                    ->required()
                                    ->suffixAction(
                                        Action::make('sendVerifyOtp')
                                            ->label('Send OTP')
                                            ->icon('heroicon-o-paper-airplane')
                                            ->action(function ($set, $get, AbhaVerifyService $verifyService) {
                                                $id = $get('search_identifier');
                                                if (empty($id)) {
                                                    Notification::make()->title('Please enter ABHA or Mobile number')->warning()->send();
                                                    return;
                                                }

                                                try {
                                                    $rawId = trim($id);
                                                    if (str_contains($rawId, '@')) {
                                                        $hint = 'abha-address';
                                                        $loginId = $rawId;
                                                    } else {
                                                        $clean = preg_replace('/[^0-9]/', '', $rawId);
                                                        $hint = strlen($clean) === 14 ? 'abha-number' : 'mobile';
                                                        $loginId = $clean;
                                                    }
                                                    $res = $verifyService->requestLoginOtp($hint, $loginId, 'abdm');
                                                    $set('verify_txn_id', $res['txnId']);
                                                    Notification::make()
                                                        ->title('Verification OTP Sent!')
                                                        ->body("OTP sent to mobile registered with this {$hint}.")
                                                        ->success()
                                                        ->send();
                                                } catch (\Throwable $e) {
                                                    Notification::make()
                                                        ->title('Failed to Send OTP')
                                                        ->body($e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            })
                                    ),
                                TextInput::make('verify_txn_id')
                                    ->label('Verification Transaction ID')
                                    ->disabled()
                                    ->dehydrated(),
                            ]),

                            TextInput::make('verify_otp_value')
                                ->label('Enter OTP Received on Mobile')
                                ->placeholder('6-digit OTP')
                                ->password()
                                ->revealable(),
                        ]),
                ];
            })
            ->action(function (Patient $record, array $data, AbhaEnrollmentService $enrolService, AbhaVerifyService $verifyService): void {
                if ($record->isAbhaVerified()) {
                    return; // Close modal
                }

                $flow = $data['flow_type'] ?? 'create';

                if ($flow === 'create') {
                    $txnId = $data['txn_id'] ?? '';
                    $otp = $data['otp_value'] ?? '';
                    $mobile = $data['comm_mobile'] ?? $record->mobile;
                    $preferred = $data['preferred_abha_address'] ?? null;

                    if (empty($txnId) || empty($otp)) {
                        Notification::make()->title('Please enter the OTP sent to mobile')->danger()->send();
                        return;
                    }

                    try {
                        $profile = $enrolService->verifyAadhaarOtp($txnId, $otp, $mobile);

                        // If user provided a preferred ABHA address, attempt to assign it
                        if (!empty($preferred)) {
                            try {
                                $enrolService->setAbhaAddress($txnId, $preferred);
                                $profile['abhaAddress'] = $preferred;
                            } catch (\Throwable $e) {
                                // Keep default assigned address if custom fails
                            }
                        }

                        $record->update([
                            'abha_number' => $profile['abhaNumber'] ?? $record->abha_number,
                            'abha_address' => $profile['abhaAddress'] ?? $record->abha_address,
                            'abdm_status' => 'verified',
                            'abdm_profile' => $profile['raw'] ?? $profile,
                            'abdm_verified_at' => now(),
                        ]);
                        $record->refresh();

                        $num = $record->formatted_abha_number ?: ($profile['abhaNumber'] ?? 'N/A');
                        $addr = $record->abha_address ?: ($profile['abhaAddress'] ?? '');

                        Notification::make()
                            ->title('ABHA Created & Linked Successfully!')
                            ->body("ABHA Number: {$num}" . ($addr ? " ({$addr})" : ""))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('ABHA Creation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                } elseif ($flow === 'verify') {
                    $txnId = $data['verify_txn_id'] ?? '';
                    $otp = $data['verify_otp_value'] ?? '';

                    if (empty($txnId) || empty($otp)) {
                        Notification::make()->title('Please enter the verification OTP')->danger()->send();
                        return;
                    }

                    try {
                        $res = $verifyService->verifyLoginOtp($txnId, $otp);

                        $record->update([
                            'abha_number' => $res['abhaNumber'] ?? $record->abha_number,
                            'abha_address' => $res['abhaAddress'] ?? $record->abha_address,
                            'abdm_status' => 'verified',
                            'abdm_profile' => $res['profile'] ?? $res['raw'],
                            'abdm_verified_at' => now(),
                        ]);
                        $record->refresh();

                        $num = $record->formatted_abha_number ?: ($res['abhaNumber'] ?? 'N/A');
                        $addr = $record->abha_address ?: ($res['abhaAddress'] ?? '');

                        Notification::make()
                            ->title('ABHA Verified & Linked to Patient!')
                            ->body("ABHA Number: {$num}" . ($addr ? " ({$addr})" : ""))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Verification Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }
            });
    }
}
