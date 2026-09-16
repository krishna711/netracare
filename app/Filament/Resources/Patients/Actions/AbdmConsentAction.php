<?php

namespace App\Filament\Resources\Patients\Actions;

use App\Models\AbdmConsent;
use App\Models\Patient;
use App\Services\Abdm\AbdmConsentService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AbdmConsentAction
{
    public static function make(): Action
    {
        return Action::make('abdmConsent')
            ->label('Records (HIU)')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('info')
            ->tooltip('ABDM Milestone 3: Request & View Patient Health Records from other Hospitals via ABHA Consent')
            ->modalHeading(fn (Patient $record): string => "ABDM External Health Records (HIU) - {$record->name} (UHID: {$record->id})")
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Send Consent Request to Patient')
            ->form(function (Patient $record): array {
                if (!$record->isAbhaVerified()) {
                    return [
                        Placeholder::make('no_abha_warning')
                            ->label('')
                            ->content(new HtmlString("
                                <div class='p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 flex items-center gap-3'>
                                    <div class='w-10 h-10 rounded-lg bg-amber-500 text-white flex items-center justify-center font-bold text-lg'>
                                        !
                                    </div>
                                    <div>
                                        <h4 class='font-bold text-amber-900 dark:text-amber-200 text-sm'>ABHA Linking Required</h4>
                                        <p class='text-xs text-amber-800 dark:text-amber-400'>This patient does not have a verified ABHA Number or ABHA Address. Please verify or link their ABHA using the <strong>ABHA</strong> button before requesting external medical records.</p>
                                    </div>
                                </div>
                            ")),
                    ];
                }

                // Load existing consent history
                $pastConsents = $record->consents()->latest()->get();
                $historyHtml = '';

                if ($pastConsents->isEmpty()) {
                    $historyHtml = "<div class='text-xs text-gray-500 italic p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700'>No consent requests initiated yet for this patient. Fill the form below to request external health records.</div>";
                } else {
                    $historyHtml = "<div class='divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-xs'>";
                    foreach ($pastConsents as $c) {
                        $badgeColor = match ($c->status) {
                            'GRANTED' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
                            'TRANSFERRED' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                            'DENIED', 'REVOKED' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300',
                            'EXPIRED' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                            default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
                        };

                        $hiTypesStr = is_array($c->hi_types) ? implode(', ', $c->hi_types) : 'All';
                        $fromStr = $c->date_from ? $c->date_from->format('d-M-Y') : 'Past';
                        $toStr = $c->date_to ? $c->date_to->format('d-M-Y') : 'Present';
                        $createdStr = $c->created_at ? $c->created_at->format('d-M-Y H:i') : '';
                        $consentIdStr = $c->consent_id ? "<span class='text-[11px] font-mono text-gray-600 dark:text-gray-400'>ID: {$c->consent_id}</span>" : "<span class='text-[11px] font-mono text-gray-400'>Req ID: " . substr($c->consent_request_id, 0, 13) . "...</span>";

                        // Transferred records count
                        $recordsInfo = '';
                        if (!empty($c->transferred_records)) {
                            $count = count($c->transferred_records);
                            $recordsInfo = "<div class='mt-1 text-[11px] text-blue-600 dark:text-blue-400 font-medium'>📁 {$count} health record(s) received and available.</div>";
                        }

                        $historyHtml .= "
                            <div class='p-3 bg-white dark:bg-gray-900 flex items-start justify-between gap-3'>
                                <div class='space-y-1'>
                                    <div class='flex items-center gap-2'>
                                        <span class='px-2 py-0.5 rounded text-[10px] font-bold {$badgeColor}'>{$c->status}</span>
                                        {$consentIdStr}
                                    </div>
                                    <div class='text-gray-700 dark:text-gray-300'><strong>Types:</strong> {$hiTypesStr}</div>
                                    <div class='text-gray-500 text-[11px]'>Range: {$fromStr} to {$toStr} • Purpose: {$c->purpose_code}</div>
                                    {$recordsInfo}
                                </div>
                                <div class='text-right text-[11px] text-gray-400 flex flex-col items-end gap-1'>
                                    <span>{$createdStr}</span>
                                </div>
                            </div>
                        ";
                    }
                    $historyHtml .= "</div>";
                }

                return [
                    Section::make('Past Consent Requests & Received Health Records')
                        ->schema([
                            Placeholder::make('consent_history')
                                ->label('')
                                ->content(new HtmlString($historyHtml)),
                        ]),

                    Section::make('Initiate New Consent Request (M3 HIU)')
                        ->description('Request patient permission via their ABHA app to fetch past prescriptions, lab tests, and hospital discharge summaries.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('doctor_name')
                                    ->label('Requesting Doctor')
                                    ->default('Dr. Vineet Gour')
                                    ->required(),

                                Select::make('purpose')
                                    ->label('Purpose of Request')
                                    ->options([
                                        'CAREMGT' => 'Care Management (Routine / Outpatient)',
                                        'PUBHLTH' => 'Public Health',
                                        'BTG' => 'Break the Glass (Emergency / Acute Care)',
                                        'RESCH' => 'Medical Research',
                                    ])
                                    ->default('CAREMGT')
                                    ->required(),
                            ]),

                            CheckboxList::make('hi_types')
                                ->label('Health Information Types to Request')
                                ->options([
                                    'Prescription' => 'Prescriptions & Medications',
                                    'DiagnosticReport' => 'Diagnostic & Lab Reports',
                                    'OPConsultation' => 'OPD Consultations & Clinical Notes',
                                    'DischargeSummary' => 'Discharge Summaries (Inpatient)',
                                    'ImmunizationRecord' => 'Immunization Records',
                                    'HealthDocumentRecord' => 'General Health Documents',
                                ])
                                ->default(['Prescription', 'DiagnosticReport', 'OPConsultation'])
                                ->columns(2)
                                ->required(),

                            Grid::make(3)->schema([
                                DatePicker::make('date_from')
                                    ->label('Records From')
                                    ->default(now()->subYears(2)->format('Y-m-d'))
                                    ->required(),

                                DatePicker::make('date_to')
                                    ->label('Records To')
                                    ->default(now()->format('Y-m-d'))
                                    ->required(),

                                DatePicker::make('data_erase_at')
                                    ->label('Access Expiry (Erase At)')
                                    ->default(now()->addMonths(1)->format('Y-m-d'))
                                    ->helperText('Access will automatically revoke after this date.')
                                    ->required(),
                            ]),
                        ]),
                ];
            })
            ->action(function (Patient $record, array $data, AbdmConsentService $consentService): void {
                if (!$record->isAbhaVerified()) {
                    return;
                }

                $abhaId = $record->abha_address ?: $record->abha_number;
                if (empty($abhaId)) {
                    Notification::make()
                        ->title('ABHA ID Missing')
                        ->body('Patient must have an ABHA Number or ABHA Address.')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    $options = [
                        'doctor_name' => $data['doctor_name'] ?? 'Dr. Vineet Gour',
                        'purpose' => $data['purpose'] ?? 'CAREMGT',
                        'hi_types' => $data['hi_types'] ?? ['Prescription', 'DiagnosticReport', 'OPConsultation'],
                        'date_from' => $data['date_from'] ?? now()->subYears(2),
                        'date_to' => $data['date_to'] ?? now(),
                        'data_erase_at' => $data['data_erase_at'] ?? now()->addMonths(1),
                    ];

                    $result = $consentService->initConsentRequest($record, $options);

                    Notification::make()
                        ->title('Consent Request Dispatched!')
                        ->body("A consent notification has been sent to {$record->name}'s ABHA app. Once approved, health records will be accessible.")
                        ->success()
                        ->duration(8000)
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to Dispatch Consent Request')
                        ->body($e->getMessage())
                        ->danger()
                        ->duration(10000)
                        ->send();
                }
            });
    }
}
