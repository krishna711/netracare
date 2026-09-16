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
use Filament\Forms\Components\Radio;
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
            ->modalWidth('7xl')
            ->extraModalWindowAttributes(['style' => 'width: 80vw !important; max-width: 80vw !important;'])
            ->modalSubmitActionLabel('Submit')
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
                    $historyHtml = "<div class='max-h-64 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg text-xs'>";
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
                        $gwId = $c->metadata['gateway_consent_request_id'] ?? null;
                        $gwIdStr = $gwId ? "<div class='text-[10px] text-gray-500 font-mono'>Gateway ID: {$gwId}</div>" : '';
                        $consentIdStr = $c->consent_id ? "<span class='text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-bold'>Artefact: {$c->consent_id}</span>" : "<span class='text-[11px] font-mono text-gray-400'>Req ID: " . substr($c->consent_request_id, 0, 13) . "...</span>";

                        // Transferred records count and preview
                        $recordsInfo = '';
                        if (!empty($c->transferred_records)) {
                            $count = count($c->transferred_records);
                            $recordsInfo = "<div class='mt-2 p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-lg border border-emerald-300 dark:border-emerald-800 space-y-2'>
                                <div class='flex items-center justify-between font-semibold text-emerald-900 dark:text-emerald-200'>
                                    <span>📁 {$count} Clinical Health Record(s) Transferred</span>
                                    <span class='text-[10px] px-2 py-0.5 rounded bg-emerald-200 dark:bg-emerald-800 text-emerald-800 dark:text-emerald-100 uppercase tracking-wider font-bold'>Verified FHIR R4</span>
                                </div>";

                            foreach ($c->transferred_records as $idx => $rec) {
                                $ref = $rec['careContextReference'] ?? ('Record #' . ($idx + 1));
                                $content = $rec['content'] ?? [];
                                if (is_string($content)) {
                                    $decoded = json_decode($content, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $content = $decoded;
                                    }
                                }

                                $summary = "<strong>Care Context:</strong> " . htmlspecialchars($ref);
                                if (is_array($content)) {
                                    $resTypes = [];
                                    if (!empty($content['entry'])) {
                                        foreach ($content['entry'] as $ent) {
                                            $resTypes[] = $ent['resource']['resourceType'] ?? 'Resource';
                                        }
                                    }
                                    $typeSummary = !empty($resTypes) ? implode(', ', array_unique($resTypes)) : 'FHIR Document';
                                    $summary .= " &bull; <span class='text-gray-600 dark:text-gray-400'>Contains: {$typeSummary}</span>";
                                }

                                $recordsInfo .= "<div class='p-2 bg-white dark:bg-gray-900 rounded border border-emerald-200 dark:border-emerald-800/60 text-[11px] text-gray-700 dark:text-gray-300'>{$summary}</div>";
                            }

                            $recordsInfo .= "</div>";
                        }

                        $historyHtml .= "
                            <div class='p-3 bg-white dark:bg-gray-900 flex items-start justify-between gap-3'>
                                <div class='space-y-1 w-full'>
                                    <div class='flex items-center gap-2'>
                                        <span class='px-2 py-0.5 rounded text-[10px] font-bold {$badgeColor}'>{$c->status}</span>
                                        {$consentIdStr}
                                    </div>
                                    {$gwIdStr}
                                    <div class='text-gray-700 dark:text-gray-300'><strong>Types:</strong> {$hiTypesStr}</div>
                                    <div class='text-gray-500 text-[11px]'>Range: {$fromStr} to {$toStr} • Purpose: {$c->purpose_code}</div>
                                    {$recordsInfo}
                                </div>
                                <div class='text-right text-[11px] text-gray-400 flex flex-col items-end gap-1 whitespace-nowrap'>
                                    <span>{$createdStr}</span>
                                </div>
                            </div>
                        ";
                    }
                    $historyHtml .= "</div>";
                }

                return [
                    Section::make('Past Consent Requests & Received Health Records')
                        ->collapsible()
                        ->schema([
                            Placeholder::make('consent_history')
                                ->label('')
                                ->content(new HtmlString($historyHtml)),
                        ]),

                    Radio::make('operation')
                        ->label('Select Action')
                        ->inline()
                        ->options([
                            'status' => '1. Check / Sync Approval Status from ABDM Gateway',
                            'fetch' => '2. Fetch Medical Records (Data Flow for Granted Consent)',
                            'new' => '3. Send New Consent Request',
                        ])
                        ->default(function () use ($pastConsents) {
                            $latest = $pastConsents->first();
                            if ($latest && $latest->status === 'GRANTED') {
                                return 'fetch';
                            }
                            if ($latest && $latest->status === 'REQUESTED') {
                                return 'status';
                            }
                            return 'new';
                        })
                        ->live(),

                    Section::make('Sync Consent Approval from ABDM Gateway')
                        ->description('Query the ABDM Gateway to verify if the patient approved the consent on their ABHA app.')
                        ->visible(fn ($get) => $get('operation') === 'status')
                        ->schema([
                            TextInput::make('gateway_consent_request_id')
                                ->label('ABDM Gateway Consent Request ID')
                                ->default(function () use ($pastConsents) {
                                    $latest = $pastConsents->first();
                                    return $latest?->metadata['gateway_consent_request_id']
                                        ?? '546ce531-0c3f-412b-8c2f-18ad1a3882f2';
                                })
                                ->helperText('Gateway Consent Request ID received from ABDM.')
                                ->required(fn ($get) => $get('operation') === 'status'),
                        ]),

                    Section::make('Fetch Medical Records (Data Flow)')
                        ->description('Request clinical records (FHIR bundles) using the approved Consent Artefact.')
                        ->visible(fn ($get) => $get('operation') === 'fetch')
                        ->schema([
                            TextInput::make('consent_artefact_id')
                                ->label('Granted Consent Artefact ID')
                                ->default(function () use ($pastConsents) {
                                    $granted = $pastConsents->firstWhere('status', 'GRANTED') ?: $pastConsents->first();
                                    return $granted?->consent_id;
                                })
                                ->helperText('Artefact ID received upon patient approval.')
                                ->required(fn ($get) => $get('operation') === 'fetch'),
                        ]),

                    Section::make('Initiate New Consent Request (M3 HIU)')
                        ->description('Request patient permission via their ABHA app to fetch past prescriptions, lab tests, and hospital discharge summaries.')
                        ->visible(fn ($get) => $get('operation') === 'new')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('doctor_name')
                                    ->label('Requesting Doctor')
                                    ->default('Dr. Vineet Gour')
                                    ->required(fn ($get) => $get('operation') === 'new'),

                                Select::make('purpose')
                                    ->label('Purpose of Request')
                                    ->options([
                                        'CAREMGT' => 'Care Management (Routine / Outpatient)',
                                        'PUBHLTH' => 'Public Health',
                                        'BTG' => 'Break the Glass (Emergency / Acute Care)',
                                        'RESCH' => 'Medical Research',
                                    ])
                                    ->default('CAREMGT')
                                    ->required(fn ($get) => $get('operation') === 'new'),

                                Select::make('hip_mode')
                                    ->label('Target Facility (Records Provider)')
                                    ->options([
                                        'NETRIKA' => 'Netrika Netralaya (Specific Facility IN2310001444)',
                                        'ALL' => 'All Linked Facilities (General Consent)',
                                    ])
                                    ->default('NETRIKA')
                                    ->helperText('Select Netrika Netralaya for self-contained testing.')
                                    ->required(fn ($get) => $get('operation') === 'new'),
                            ]),

                            CheckboxList::make('hi_types')
                                ->label('Health Information Types to Request')
                                ->options([
                                    'OPConsultation' => 'OPD Consultations & Clinical Notes',
                                    'Prescription' => 'Prescriptions & Medications',
                                    'DiagnosticReport' => 'Diagnostic & Lab Reports',
                                    'DischargeSummary' => 'Discharge Summaries (Inpatient)',
                                    'ImmunizationRecord' => 'Immunization Records',
                                    'HealthDocumentRecord' => 'General Health Documents',
                                ])
                                ->default(['OPConsultation'])
                                ->columns(3)
                                ->required(fn ($get) => $get('operation') === 'new'),

                            Grid::make(3)->schema([
                                DatePicker::make('date_from')
                                    ->label('Records From')
                                    ->default(now()->subYears(2)->format('Y-m-d'))
                                    ->required(fn ($get) => $get('operation') === 'new'),

                                DatePicker::make('date_to')
                                    ->label('Records To')
                                    ->default(now()->format('Y-m-d'))
                                    ->required(fn ($get) => $get('operation') === 'new'),

                                DatePicker::make('data_erase_at')
                                    ->label('Access Expiry (Erase At)')
                                    ->default(now()->addMonths(1)->format('Y-m-d'))
                                    ->helperText('Access will automatically revoke after this date.')
                                    ->required(fn ($get) => $get('operation') === 'new'),
                            ]),
                        ]),
                ];
            })
            ->action(function (Patient $record, array $data, AbdmConsentService $consentService): void {
                if (!$record->isAbhaVerified()) {
                    return;
                }

                $op = $data['operation'] ?? 'status';
                $latest = $record->consents()->latest()->first();

                // 1. Check Status
                if ($op === 'status') {
                    if (!$latest) {
                        Notification::make()->title('No consent requests found')->warning()->send();
                        return;
                    }
                    try {
                        $reqId = !empty($data['gateway_consent_request_id'])
                            ? trim($data['gateway_consent_request_id'])
                            : ($latest->metadata['gateway_consent_request_id'] ?? $latest->consent_request_id);

                        $res = $consentService->getConsentStatus($reqId);
                        $latest->refresh();
                        $status = $latest->status;
                        Notification::make()
                            ->title("Consent Status: {$status}")
                            ->body($status === 'GRANTED'
                                ? "Consent GRANTED by Patient! Artefact ID: {$latest->consent_id}. You can now select 'Fetch Medical Records'."
                                : "Gateway reports status: {$status} (Request ID: {$reqId}). If recently approved in PHR app, give it a few seconds and check again.")
                            ->success()
                            ->duration(8000)
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Failed to check status')->body($e->getMessage())->danger()->send();
                    }
                    return;
                }

                // 2. Fetch Health Records
                if ($op === 'fetch') {
                    $granted = $record->consents()->where('status', 'GRANTED')->latest()->first() ?: $latest;
                    if (!empty($data['consent_artefact_id']) && $granted) {
                        $granted->update(['consent_id' => trim($data['consent_artefact_id'])]);
                    }
                    if (!$granted || empty($granted->consent_id)) {
                        Notification::make()->title('Consent has not yet been GRANTED or consent ID is missing')->warning()->send();
                        return;
                    }
                    try {
                        $res = $consentService->requestHealthInformation($granted);
                        $granted->refresh();
                        $recCount = count($granted->transferred_records ?? []);
                        Notification::make()
                            ->title('Health Information Request Dispatched!')
                            ->body($recCount > 0
                                ? "Successfully fetched {$recCount} clinical record(s)! You can view the records above in the history."
                                : 'Requested encrypted clinical records from facility. Data flow is in progress.')
                            ->success()
                            ->duration(10000)
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to dispatch data request')
                            ->body($e->getMessage())
                            ->danger()
                            ->duration(12000)
                            ->send();
                    }
                    return;
                }

                // 3. New Consent Request
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
                        'hi_types' => $data['hi_types'] ?? ['OPConsultation'],
                        'hip_id' => ($data['hip_mode'] ?? 'NETRIKA') === 'NETRIKA' ? 'IN2310001444' : null,
                        'date_from' => $data['date_from'] ?? now()->subYears(2)->format('Y-m-d'),
                        'date_to' => $data['date_to'] ?? now()->format('Y-m-d'),
                        'data_erase_at' => $data['data_erase_at'] ?? now()->addMonths(1)->format('Y-m-d'),
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
