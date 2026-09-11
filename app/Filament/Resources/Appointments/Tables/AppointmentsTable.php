<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Models\Appointment;
use App\Models\Payment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Consultation;
use App\Models\Ipd;
use App\Models\IpdV1;
use App\Models\IpdV2;
use App\Models\OptometristWorksheet;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use App\Services\Abdm\CareContextService;
use App\Services\Abdm\FhirBundleService;
use Illuminate\Support\Facades\DB;

if (!function_exists('getSettingOptions')) {
    function getSettingOptions($key) {
        try {
            $val = DB::table('settings')->where('key', $key)->value('value');
            if ($val) {
                $arr = explode("\n", str_replace("\r", "", $val));
                return array_filter(array_map('trim', $arr));
            }
        } catch (\Exception $e) {}
        return [];
    }
}
if (!function_exists('getSphOptions')) {
    function getSphOptions() {
        $opts = ['0' => '0'];
        for ($i = -20; $i <= 20; $i += 0.25) { if ($i == 0) continue; $v = ($i > 0 ? '+' : '') . number_format($i, 2); $opts[$v] = $v; }
        return $opts;
    }
}
if (!function_exists('getAxisOptions')) {
    function getAxisOptions() {
        $opts = ['0' => '0']; for ($i = 5; $i <= 180; $i += 5) { $opts[(string)$i] = (string)$i; } return $opts;
    }
}
if (!function_exists('getVnOptions')) {
    function getVnOptions() {
        return ['0'=>'0', 'No PL'=>'No PL', '1/60'=>'1/60', '2/60'=>'2/60', '3/60'=>'3/60', '4/60'=>'4/60', '5/60'=>'5/60', '6/60'=>'6/60', '6/36'=>'6/36', '6/24'=>'6/24', '6/18'=>'6/18', '6/12'=>'6/12', '6/9P'=>'6/9P', '6/9'=>'6/9', '6/6P'=>'6/6P', '6/6'=>'6/6', 'CF'=>'CF', 'CF1'=>'CF1', 'CF2'=>'CF2', 'HM'=>'HM', 'PR+'=>'PR+', 'PR-'=>'PR-', 'PL+'=>'PL+', 'PL-'=>'PL-'];
    }
}
if (!function_exists('getIopOptions')) {
    function getIopOptions() {
        return ['0'=>'0', '1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4', '5'=>'5', '6'=>'6', '7'=>'7', '8'=>'8', '9'=>'9', '10'=>'10', '11'=>'11', '12'=>'12', '13'=>'13', '14'=>'14', '15'=>'15', '16'=>'16', '17'=>'17', '18'=>'18', '19'=>'19', '20'=>'20', '21'=>'21', '22'=>'22', '23'=>'23', '24'=>'24', '25'=>'25', '26'=>'26', '27'=>'27', '28'=>'28', '29'=>'29', '30'=>'30', '35'=>'35', '40'=>'40', '45'=>'45', '50'=>'50', '60'=>'60', 'UR'=>'UR'];
    }
}
if (!function_exists('getSchOptions')) {
    function getSchOptions() {
        $opts = ['0'=>'0']; for($i=1; $i<=30; $i++) $opts[(string)$i] = (string)$i; return $opts;
    }
}
if (!function_exists('getNearAddOptions')) {
    function getNearAddOptions() {
        $opts = ['0' => '0']; for($i=0.75; $i<=3.50; $i+=0.25) { $v=number_format($i,2); $opts[$v]=$v; } return $opts;
    }
}
if (!function_exists('getNearVnOptions')) {
    function getNearVnOptions() {
        return ['0'=>'0', 'N36'=>'N36', 'N30'=>'N30', 'N24'=>'N24', 'N18'=>'N18', 'N12'=>'N12', 'N10'=>'N10', 'N8'=>'N8', 'N6'=>'N6', 'N5'=>'N5'];
    }
}

class AppointmentsTable
{
    private static function persistConsultation(Appointment $record, array $data): void
    {
        // ── Save Optometrist Worksheet ─────────────────────────────
        $optFields = [
            'optometrist_name','optometrist_id_no',
            'va_re_unaided','va_re_with_glass','va_re_near','va_re_pg_sph','va_re_pg_cyl','va_re_pg_axis',
            'va_le_unaided','va_le_with_glass','va_le_near','va_le_pg_sph','va_le_pg_cyl','va_le_pg_axis',
            'dry_re_sph','dry_re_cyl','dry_re_axis','dry_re_vision',
            'dry_le_sph','dry_le_cyl','dry_le_axis','dry_le_vision',
            'dry_remark','dry_dd',
            'wet_re_sph','wet_re_cyl','wet_re_axis','wet_re_vision',
            'wet_le_sph','wet_le_cyl','wet_le_axis','wet_le_vision',
            'fp_re_sph','fp_re_cyl','fp_re_axis','fp_re_bcva','fp_re_near_add',
            'fp_le_sph','fp_le_cyl','fp_le_axis','fp_le_bcva','fp_le_near_add',
            'fp_remark','fp_amount_glass',
        ];
        $optData = [];
        foreach ($optFields as $f) {
            $key = 'opt_' . $f;
            if (array_key_exists($key, $data)) {
                $optData[$f] = $data[$key];
                unset($data[$key]);
            }
        }
        // Also unset the top-level opt_ name/id keys
        unset($data['opt_optometrist_name'], $data['opt_optometrist_id_no']);
        if (!empty(array_filter($optData))) {
            OptometristWorksheet::updateOrCreate(
                ['appointment_id' => $record->id, 'patient_id' => $record->patient_id],
                $optData
            );
        }
        $consultationData = $data;

        // Strip IPD V2 fields and misc non-consultation fields
        $ipdVersion = (int) env('IPD_VERSION', 1);
        $ipd2Keys   = ['ipd2_date_of_admission','ipd2_date_of_surgery','ipd2_date_of_discharge',
                       'ipd2_final_diagnosis','ipd2_procedure_surgery','ipd2_surgeon_name',
                       'ipd2_investigation','ipd2_condition_on_discharge','ipd2_next_followup_date',
                       'ipd2_post_operative_rest','ipd2_special_instruction','ipd2_instruction',
                       'ipd2_prescription_list'];
        foreach ($ipd2Keys as $k) unset($consultationData[$k]);
        unset($consultationData['inpatient_details'], $consultationData['patient_name'],
              $consultationData['technician'], $consultationData['ipd2_id_badge']);

        // Tags → comma string
        $tagKeys = ['complaint','previous_history','prescription_text_re','tests_le',
                    'prescription_text','tests','diagnosis','notes','advice'];
        foreach ($tagKeys as $key) {
            if (isset($consultationData[$key]) && is_array($consultationData[$key])) {
                $consultationData[$key] = implode(', ', $consultationData[$key]);
            }
        }

        // Prescription repeater → pipe+tilde string
        if (isset($consultationData['prescription_list']) && is_array($consultationData['prescription_list'])) {
            $consultationData['prescription_list'] = array_values(array_filter(
                $consultationData['prescription_list'],
                fn (array $pres): bool => (
                    filled($pres['type'] ?? null) ||
                    filled($pres['medicine'] ?? null) ||
                    filled($pres['frequency'] ?? null) ||
                    filled($pres['time'] ?? null) ||
                    filled($pres['duration'] ?? null)
                )
            ));

            $lines = [];
            foreach ($consultationData['prescription_list'] as $pres) {
                $lines[] = implode(' | ', [
                    $pres['type'] ?? '',
                    $pres['medicine'] ?? '',
                    $pres['frequency'] ?? '',
                    $pres['time'] ?? '',
                    $pres['duration'] ?? '',
                ]);
            }
            $consultationData['prescription'] = count($lines) ? implode(' ~ ', $lines) : null;
        }
        unset($consultationData['prescription_list']);

        if (isset($consultationData['lens']) && is_array($consultationData['lens'])) {
            $consultationData['lens'] = implode(',', $consultationData['lens']);
        }

        Consultation::updateOrCreate(
            ['appointment_id' => $record->id, 'patient_id' => $record->patient_id],
            $consultationData
        );

        // ── Save IPD based on version ──────────────────────────────────
        if ($ipdVersion === 2) {
            // Build prescription string from repeater
            $rx = '';
            if (!empty($data['ipd2_prescription_list']) && is_array($data['ipd2_prescription_list'])) {
                $data['ipd2_prescription_list'] = array_values(array_filter(
                    $data['ipd2_prescription_list'],
                    fn (array $pres): bool => (
                        filled($pres['type'] ?? null) ||
                        filled($pres['medicine'] ?? null) ||
                        filled($pres['frequency'] ?? null) ||
                        filled($pres['time'] ?? null) ||
                        filled($pres['duration'] ?? null)
                    )
                ));

                $rxLines = [];
                foreach ($data['ipd2_prescription_list'] as $pres) {
                    $rxLines[] = implode(' | ', [
                        $pres['type'] ?? '',
                        $pres['medicine'] ?? '',
                        $pres['frequency'] ?? '',
                        $pres['time'] ?? '',
                        $pres['duration'] ?? '',
                    ]);
                }
                $rx = implode(' ~ ', $rxLines);
            }

            IpdV2::updateOrCreate(
                ['appointment_id' => $record->id, 'patient_id' => $record->patient_id],
                [
                    'date_of_admission'                 => $data['ipd2_date_of_admission']  ?? null,
                    'date_of_surgery'                   => $data['ipd2_date_of_surgery']    ?? null,
                    'date_of_discharge'                 => $data['ipd2_date_of_discharge']  ?? null,
                    'final_diagnosis'                   => $data['ipd2_final_diagnosis']    ?? null,
                    'procedure_surgery'                 => $data['ipd2_procedure_surgery']  ?? null,
                    'surgeon_name'                      => $data['ipd2_surgeon_name']       ?? null,
                    'investigation_during_hospitalization' => $data['ipd2_investigation']   ?? null,
                    'condition_on_discharge'            => $data['ipd2_condition_on_discharge'] ?? null,
                    'next_followup_date'                => $data['ipd2_next_followup_date'] ?? null,
                    'post_operative_rest'               => $data['ipd2_post_operative_rest']?? null,
                    'special_instruction'               => $data['ipd2_special_instruction']?? null,
                    'instruction'                       => $data['ipd2_instruction']        ?? null,
                    'prescription'                      => $rx ?: null,
                ]
            );
        } else {
            // V1 — legacy rich-text
            if (!empty($data['inpatient_details'])) {
                IpdV1::updateOrCreate(
                    ['appointment_id' => $record->id, 'patient_id' => $record->patient_id],
                    ['details' => $data['inpatient_details']]
                );
            }
        }
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('MRD')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('patient.id')
                    ->label('UHID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('appointment_time')
                    ->label('Time')
                    ->dateTime('d-M-Y H:i A')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('visited')
                    ->boolean()
                    ->label('Visited')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->filters([
                \Filament\Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->whereDate('appointment_time', now()))
                    ->toggle(),
                \Filament\Tables\Filters\Filter::make('yesterday')
                    ->label('Yesterday')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->whereDate('appointment_time', now()->subDay()))
                    ->toggle(),
                \Filament\Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('To Date'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('appointment_time', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('appointment_time', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                \Filament\Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Doctor')
                    ->relationship('doctor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Action::make('filter_today')
                    ->label('Today')
                    ->icon('heroicon-o-calendar')
                    ->color('primary')
                    ->action(function ($livewire) {
                        $livewire->tableFilters['today']['isActive'] = true;
                        $livewire->tableFilters['yesterday']['isActive'] = false;
                    }),
                Action::make('filter_yesterday')
                    ->label('Yesterday')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->action(function ($livewire) {
                        $livewire->tableFilters['yesterday']['isActive'] = true;
                        $livewire->tableFilters['today']['isActive'] = false;
                    }),
                Action::make('filter_all')
                    ->label('All')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->action(function ($livewire) {
                        $livewire->tableFilters['today']['isActive'] = false;
                        $livewire->tableFilters['yesterday']['isActive'] = false;
                        $livewire->tableFilters['date_range']['from'] = null;
                        $livewire->tableFilters['date_range']['until'] = null;
                        $livewire->tableFilters['doctor_id']['value'] = null;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('markComplete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn(Appointment $record) => $record->update(['visited' => 1]))
                    ->visible(fn(Appointment $record) => !$record->visited)
                    ->label('✔'),
                Action::make('wa')
                    ->label('WA')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn(Appointment $record): string => "https://wa.me/+91{$record->patient->mobile}?text=" . urlencode("Hello {$record->patient->name}, Welcome to Netrika Netralaya Bhopal. Your registration has been confirmed."))
                    ->openUrlInNewTab(),
                Action::make('eyeCard')
                    ->label('EC')
                    ->icon('heroicon-o-identification')
                    ->url(fn(Appointment $record): string => url("/print/patient/card/{$record->patient_id}/{$record->id}"))
                    ->openUrlInNewTab(),
                Action::make('details')
                    ->label('Print')
                    ->icon('heroicon-o-document-text')
                    ->form([
                        CheckboxList::make('print_options')
                            ->label('')
                            ->options([
                                'patientinfo' => 'Patient Details',
                                'diagnosis' => 'Diagnosis',
                                'treatment' => 'Treatment',
                                'eyedetails' => 'Eye Details',
                                'disablebanner' => 'Enable Top Banner'
                            ])
                            ->default(['patientinfo'])
                            ->columns(5)
                            ->gridDirection('row')
                    ])
                    ->modalWidth('4xl')
                    ->modalHeading('Select Print Options')
                    ->modalSubmitActionLabel('Print')
                    ->action(function (Appointment $record, array $data, $livewire) {
                        $selectedOptions = implode(':', $data['print_options'] ?? []) . ':';
                        $url = url("/print/patient/details/{$record->patient_id}/{$record->id}?option={$selectedOptions}");
                        $livewire->js("window.open('{$url}', '_blank');");
                    }),

                Action::make('abdmLink')
                    ->label(function (Appointment $record): string {
                        $cc = $record->careContext;
                        return ($cc && in_array($cc->status, ['linked', 'registered'])) ? 'ABHA ✓' : 'ABHA Link';
                    })
                    ->icon('heroicon-o-link')
                    ->color(function (Appointment $record): string {
                        $cc = $record->careContext;
                        return ($cc && in_array($cc->status, ['linked', 'registered'])) ? 'success' : ($record->patient?->isAbhaVerified() ? 'info' : 'gray');
                    })
                    ->tooltip(function (Appointment $record): string {
                        $cc = $record->careContext;
                        if ($cc && in_array($cc->status, ['linked', 'registered'])) {
                            return "Registered on " . ($cc->linked_at?->format('d-M-Y H:i') ?? '');
                        }
                        return $record->patient?->isAbhaVerified() ? "Click to link visit to patient's ABHA account" : "Patient has no verified ABHA. Click to link or verify.";
                    })
                    ->modalHeading(fn (Appointment $record): string => "ABDM Care Context Linking - Visit #{$record->id}")
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel(function (Appointment $record): string {
                        $cc = $record->careContext;
                        return ($cc && in_array($cc->status, ['linked', 'registered'])) ? 'Re-link / Update' : 'Link Visit to ABHA';
                    })
                    ->form(function (Appointment $record): array {
                        $patient = $record->patient;
                        $cc = $record->careContext;
                        $isLinked = $cc && in_array($cc->status, ['linked', 'registered']);

                        $abhaStatusHtml = $patient?->isAbhaVerified()
                            ? "<span class='px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 font-semibold'>Verified</span> {$patient->formatted_abha_number} ({$patient->abha_address})"
                            : "<span class='px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 font-semibold'>Not Verified</span>";

                        $linkStatusHtml = $isLinked
                            ? "<div class='p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800 font-medium'>✓ This visit is registered for patient's ABHA account (Ref: <code>{$cc->care_context_reference}</code>).</div>"
                            : "<div class='p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800'>Linking this visit will register the Care Context with ABDM for patient discovery and records access.</div>";

                        return [
                            \Filament\Forms\Components\Placeholder::make('patient_info')
                                ->label('Patient Details')
                                ->content(new HtmlString("
                                    <div class='text-sm space-y-1'>
                                        <div><strong>Name:</strong> {$patient?->name} (UHID: {$patient?->id})</div>
                                        <div><strong>ABHA Status:</strong> {$abhaStatusHtml}</div>
                                    </div>
                                ")),
                            \Filament\Forms\Components\Placeholder::make('context_preview')
                                ->label('Care Context Details')
                                ->content(new HtmlString("
                                    <div class='text-sm space-y-1 mb-2'>
                                        <div><strong>Visit Ref:</strong> <code>OPD-APP-{$record->id}</code></div>
                                        <div><strong>Encounter Date:</strong> " . ($record->appointment_time ? date('d-M-Y H:i A', strtotime($record->appointment_time)) : date('d-M-Y')) . "</div>
                                        <div><strong>Doctor:</strong> " . ($record->doctor?->name ?? 'Attending Specialist') . "</div>
                                    </div>
                                    {$linkStatusHtml}
                                ")),
                        ];
                    })
                    ->action(function (Appointment $record, CareContextService $contextService) {
                        $patient = $record->patient;
                        if (!$patient || !$patient->isAbhaVerified()) {
                            Notification::make()
                                ->title('ABHA Not Verified')
                                ->body('Please verify the patient\'s ABHA number first before linking clinical visits.')
                                ->warning()
                                ->send();
                            return;
                        }

                        try {
                            $context = $contextService->createOrGetForAppointment($record);
                            $res = $contextService->linkCareContext($context);

                            if (($res['status'] ?? '') === 'registered_locally') {
                                Notification::make()
                                    ->title('Care Context Registered')
                                    ->body($res['message'] ?? "Registered as {$res['care_context_reference']}.")
                                    ->info()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Visit Linked to ABHA Successfully!')
                                    ->body("Care Context {$res['care_context_reference']} registered with ABDM.")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Care Context Linking Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('abdmFhir')
                    ->label('FHIR R4')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->tooltip('View & Export official NRCES FHIR R4 Bundle (Milestone 2)')
                    ->modalHeading(fn (Appointment $record): string => "NRCES FHIR R4 Health Record — Visit #{$record->id}")
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form(function (Appointment $record, FhirBundleService $fhirService): array {
                        $patient = $record->patient;
                        $consultation = $record->consultation ?: Consultation::where('appointment_id', $record->id)->first();
                        
                        try {
                            $opBundle = $fhirService->buildOpConsultationBundle($record);
                            $rxBundle = $fhirService->buildPrescriptionBundle($record);
                            $opJson = json_encode($opBundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            $rxJson = json_encode($rxBundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        } catch (\Throwable $e) {
                            $opJson = json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
                            $rxJson = $opJson;
                        }

                        $diagCount = count($fhirService->parseList($consultation?->diagnosis));
                        $rxCount = count($fhirService->parsePrescriptions($consultation?->prescription));
                        $downloadOpUrl = url("/abdm/fhir/{$record->id}/opconsult");
                        $downloadRxUrl = url("/abdm/fhir/{$record->id}/prescription");

                        $summaryHtml = "
                            <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; font-size: 13px;'>
                                <div style='background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; color: #065f46;'>
                                    <div style='font-weight: 700; color: #047857; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Patient & ABHA</div>
                                    <div style='color: #111827; font-weight: 600; font-size: 14px;'>{$patient?->name}</div>
                                    <div style='font-family: monospace; color: #059669; font-size: 12px; margin-top: 3px; font-weight: 600;'>" . ($patient?->formatted_abha_number ?: 'No ABHA') . "</div>
                                </div>
                                <div style='background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; color: #1e40af;'>
                                    <div style='font-weight: 700; color: #1d4ed8; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>NRCES Standard</div>
                                    <div style='color: #111827; font-weight: 600;'>OPConsultRecord & Rx</div>
                                    <div style='font-family: monospace; color: #2563eb; font-size: 12px; margin-top: 3px; font-weight: 600;'>Ref: OPD-APP-{$record->id}</div>
                                </div>
                                <div style='background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 12px; color: #6b21a8;'>
                                    <div style='font-weight: 700; color: #7e22ce; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Clinical Content</div>
                                    <div style='color: #111827; font-weight: 600;'>{$diagCount} Diagnosis, {$rxCount} Medicines</div>
                                    <div style='color: #6b7280; font-size: 12px; margin-top: 3px;'>Follow-up: " . ($consultation?->followup_date ?: 'Not set') . "</div>
                                </div>
                            </div>
                            <div style='display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 16px; padding: 4px 0;'>
                                <a href='{$downloadOpUrl}' target='_blank' style='display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: #059669; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'>
                                    <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='width: 14px; height: 14px; min-width: 14px; max-width: 14px; display: inline-block; vertical-align: middle;'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path><polyline points='7 10 12 15 17 10'></polyline><line x1='12' y1='15' x2='12' y2='3'></line></svg>
                                    Download OP Consultation JSON
                                </a>
                                <a href='{$downloadRxUrl}' target='_blank' style='display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'>
                                    <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='width: 14px; height: 14px; min-width: 14px; max-width: 14px; display: inline-block; vertical-align: middle;'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path><polyline points='7 10 12 15 17 10'></polyline><line x1='12' y1='15' x2='12' y2='3'></line></svg>
                                    Download Prescription JSON
                                </a>
                                <span style='font-size: 12px; color: #059669; font-weight: 600; margin-left: auto; display: inline-flex; align-items: center; gap: 4px;'>
                                    <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' style='width: 14px; height: 14px; display: inline-block;'><polyline points='20 6 9 17 4 12'></polyline></svg>
                                    Validated NRCES FHIR R4 Compliant
                                </span>
                            </div>
                        ";

                        return [
                            \Filament\Forms\Components\Placeholder::make('summary')
                                ->hiddenLabel()
                                ->content(new HtmlString($summaryHtml)),
                            Tabs::make('fhir_tabs')
                                ->tabs([
                                    Tabs\Tab::make('op_consult')
                                        ->label('OP Consultation Record (JSON)')
                                        ->schema([
                                            Textarea::make('op_fhir_json')
                                                ->hiddenLabel()
                                                ->rows(18)
                                                ->default($opJson)
                                                ->disabled()
                                                ->extraInputAttributes(['class' => 'font-mono text-xs leading-relaxed']),
                                        ]),
                                    Tabs\Tab::make('prescription')
                                        ->label('Prescription Document (JSON)')
                                        ->schema([
                                            Textarea::make('rx_fhir_json')
                                                ->hiddenLabel()
                                                ->rows(18)
                                                ->default($rxJson)
                                                ->disabled()
                                                ->extraInputAttributes(['class' => 'font-mono text-xs leading-relaxed']),
                                        ]),
                                ]),
                        ];
                    }),

                Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->url(fn (Appointment $record): string => url("/admin/payments/create?patient_id={$record->patient_id}"))
                    ->visible(fn() => auth()->user() && auth()->user()->hasPermissionTo('manage_payments')),
                Action::make('consultation')
                    ->label('Consultation')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->fillForm(function (Appointment $record): array {
                        $consultation = Consultation::where('appointment_id', $record->id)->first();
                        $data = $consultation ? $consultation->toArray() : [];
                        $data['patient_name'] = $record->patient->name ?? '';

                        $ipdVersion = (int) env('IPD_VERSION', 1);
                        if ($ipdVersion === 2) {
                            $ipd = IpdV2::where('appointment_id', $record->id)->first();
                            if ($ipd) {
                                $data['ipd2_date_of_admission']  = $ipd->date_of_admission?->format('Y-m-d');
                                $data['ipd2_date_of_surgery']    = $ipd->date_of_surgery?->format('Y-m-d');
                                $data['ipd2_date_of_discharge']  = $ipd->date_of_discharge?->format('Y-m-d');
                                $data['ipd2_final_diagnosis']    = $ipd->final_diagnosis;
                                $data['ipd2_procedure_surgery']  = $ipd->procedure_surgery;
                                $data['ipd2_surgeon_name']       = $ipd->surgeon_name;
                                $data['ipd2_investigation']      = $ipd->investigation_during_hospitalization;
                                $data['ipd2_condition_on_discharge'] = $ipd->condition_on_discharge;
                                $data['ipd2_next_followup_date'] = $ipd->next_followup_date?->format('Y-m-d');
                                $data['ipd2_post_operative_rest']= $ipd->post_operative_rest;
                                $data['ipd2_special_instruction']= $ipd->special_instruction;
                                $data['ipd2_instruction']        = $ipd->instruction;

                                // Parse prescription into repeater format
                                $data['ipd2_prescription_list'] = [];
                                if (!empty($ipd->prescription)) {
                                    foreach (array_filter(explode('~', $ipd->prescription)) as $line) {
                                        $parts = array_map('trim', explode('|', $line));
                                        $data['ipd2_prescription_list'][] = [
                                            'type'      => $parts[0] ?? '',
                                            'medicine'  => $parts[1] ?? '',
                                            'frequency' => $parts[2] ?? '',
                                            'time'      => $parts[3] ?? '',
                                            'duration'  => $parts[4] ?? '',
                                        ];
                                    }
                                }
                            }
                        } else {
                            $ipd = IpdV1::where('appointment_id', $record->id)->first();
                            if ($ipd) {
                                $data['inpatient_details'] = $ipd->details;
                            }
                        }

                        // Load Optometrist Worksheet
                        $opt = OptometristWorksheet::where('appointment_id', $record->id)->first();
                        if ($opt) {
                            $optFields = [
                                'optometrist_name','optometrist_id_no',
                                'va_re_unaided','va_re_with_glass','va_re_near','va_re_pg_sph','va_re_pg_cyl','va_re_pg_axis',
                                'va_le_unaided','va_le_with_glass','va_le_near','va_le_pg_sph','va_le_pg_cyl','va_le_pg_axis',
                                'dry_re_sph','dry_re_cyl','dry_re_axis','dry_re_vision',
                                'dry_le_sph','dry_le_cyl','dry_le_axis','dry_le_vision',
                                'dry_remark','dry_dd',
                                'wet_re_sph','wet_re_cyl','wet_re_axis','wet_re_vision',
                                'wet_le_sph','wet_le_cyl','wet_le_axis','wet_le_vision',
                                'fp_re_sph','fp_re_cyl','fp_re_axis','fp_re_bcva','fp_re_near_add',
                                'fp_le_sph','fp_le_cyl','fp_le_axis','fp_le_bcva','fp_le_near_add',
                                'fp_remark','fp_amount_glass',
                            ];
                            foreach ($optFields as $f) {
                                $data['opt_' . $f] = $opt->$f;
                            }
                        }
                        
                        // Handle TagsInput data (array format) to string format
                        $tagKeys = ['complaint', 'previous_history', 'prescription_text_re', 'tests_le', 'prescription_text', 'tests', 'diagnosis', 'notes', 'advice'];
                        foreach ($tagKeys as $key) {
                            if (isset($data[$key]) && is_string($data[$key])) {
                                $data[$key] = array_filter(array_map('trim', explode(',', $data[$key])));
                            }
                        }

                        $data['prescription_list'] = [];
                        if (!empty($data['prescription'])) {
                            $lines = array_filter(explode('~', $data['prescription']));
                            foreach ($lines as $line) {
                                $parts = array_map('trim', explode('|', $line));
                                if (count($parts) >= 2) {
                                    $data['prescription_list'][] = [
                                        'type' => $parts[0] ?? '',
                                        'medicine' => $parts[1] ?? '',
                                        'frequency' => $parts[2] ?? '',
                                        'time' => $parts[3] ?? '',
                                        'duration' => $parts[4] ?? ''
                                    ];
                                } else {
                                    $data['prescription_list'][] = [
                                        'type' => '',
                                        'medicine' => $line,
                                        'frequency' => '',
                                        'time' => '',
                                        'duration' => ''
                                    ];
                                }
                            }
                        }

                        if (isset($data['lens']) && is_string($data['lens'])) {
                            $data['lens'] = explode(',', $data['lens']);
                        }
                        return $data;
                    })
                    ->form([
                        Tabs::make('Consultation Tabs')
                            ->tabs([
                                Tabs\Tab::make('Glass')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('patient_name')->label('Patient Name')->default(fn(Appointment $record) => $record->patient->name)->disabled(),
                                            TextInput::make('referred_by')->label('Referred By'),
                                        ]),
                                        Grid::make(1)->schema([
                                            Grid::make(2)->schema([
                                                Section::make('Right Eye')->schema([
                                                    Grid::make(5)->schema([
                                                        Select::make('r_vn')->label('Vn')->options(getVnOptions()),
                                                        Select::make('r_vnglass')->label('Vn Gls')->options(getVnOptions()),
                                                        Select::make('r_iop')->label('IOP')->options(getIopOptions()),
                                                        Select::make('r_at')->label('AT')->options(getIopOptions()),
                                                        Select::make('r_sch')->label('Sch')->options(getSchOptions()),
                                                    ]),
                                                ]),
                                                Section::make('Right Eye')->schema([
                                                    Grid::make(6)->schema([
                                                        Select::make('r_sph')->label('SPH')->options(getSphOptions()),
                                                        Select::make('r_cyl')->label('CYL')->options(getSphOptions()),
                                                        Select::make('r_axis')->label('AXIS')->options(getAxisOptions()),
                                                        Select::make('r_disvn')->label('Dis.Vn')->options(getVnOptions()),
                                                        Select::make('r_nearadd')->label('NearAdd')->options(getNearAddOptions()),
                                                        Select::make('r_nearvn')->label('Near Vn')->options(getNearVnOptions()),
                                                    ]),
                                                ]),
                                            ]),
                                            Grid::make(2)->schema([
                                                Section::make('Left Eye')->schema([
                                                    Grid::make(5)->schema([
                                                        Select::make('l_vn')->label('Vn')->options(getVnOptions()),
                                                        Select::make('l_vnglass')->label('Vn Gls')->options(getVnOptions()),
                                                        Select::make('l_iop')->label('IOP')->options(getIopOptions()),
                                                        Select::make('l_at')->label('AT')->options(getIopOptions()),
                                                        Select::make('l_sch')->label('Sch')->options(getSchOptions()),
                                                    ]),
                                                ]),
                                                Section::make('Left Eye')->schema([
                                                    Grid::make(6)->schema([
                                                        Select::make('l_sph')->label('SPH')->options(getSphOptions()),
                                                        Select::make('l_cyl')->label('CYL')->options(getSphOptions()),
                                                        Select::make('l_axis')->label('AXIS')->options(getAxisOptions()),
                                                        Select::make('l_disvn')->label('Dis.Vn')->options(getVnOptions()),
                                                        Select::make('l_nearadd')->label('NearAdd')->options(getNearAddOptions()),
                                                        Select::make('l_nearvn')->label('Near Vn')->options(getNearVnOptions()),
                                                    ]),
                                                ]),
                                            ]),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextInput::make('ipd')->label('IPD'),
                                            TextInput::make('technician')->label('Technician'),
                                        ]),
                                        CheckboxList::make('lens')
                                            ->label('Lens')
                                            ->options([
                                                'Bifocal' => 'Bifocal', 'Progressive' => 'Progressive', 'Only For Distance' => 'Only For Distance', 'Dark Google' => 'Dark Google', 'Hi-Index' => 'Hi-Index', 'Photochromatic' => 'Photochromatic', 'Polarized' => 'Polarized', 'Protective Glass' => 'Protective Glass', 'Separate' => 'Separate', 'Constant Use' => 'Constant Use', 'Near Only' => 'Near Only', 'CL' => 'CL', 'Anti Glare' => 'Anti Glare', 'Blue Light Cutter' => 'Blue Light Cutter', 'UV Protective' => 'UV Protective', 'Trivax' => 'Trivax', 'Dry Ratino Scopy' => 'Dry Ratino Scopy'
                                            ])
                                            ->columns(6)
                                            ->gridDirection('row'),
                                    ]),
                                Tabs\Tab::make('Diagnosis')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TagsInput::make('complaint')->label('Complaint Of')->suggestions(fn() => getSettingOptions('complaints'))->separator(',')->placeholder('Type and press comma to add'),
                                            TagsInput::make('previous_history')->label('Medical History')->suggestions(fn() => getSettingOptions('previous_history'))->separator(',')->placeholder('Type and press comma to add'),
                                            TagsInput::make('prescription_text_re')->label('RE-A/S')->suggestions(fn() => getSettingOptions('re_as'))->separator(',')->placeholder('Type and press comma to add'),
                                            TagsInput::make('tests_le')->label('LE-A/S')->suggestions(fn() => getSettingOptions('le_as'))->separator(',')->placeholder('Type and press comma to add'),
                                            TagsInput::make('prescription_text')->label('RE-Fundus')->suggestions(fn() => getSettingOptions('re_fundus'))->separator(',')->placeholder('Type and press comma to add'),
                                            TagsInput::make('tests')->label('LE-Fundus')->suggestions(fn() => getSettingOptions('le_fundus'))->separator(',')->placeholder('Type and press comma to add'),
                                        ]),
                                        TagsInput::make('diagnosis')->label('Diagnosis')->suggestions(fn() => getSettingOptions('diagnosis'))->separator(',')->columnSpanFull()->placeholder('Type and press comma to add'),
                                        Grid::make(2)->schema([
                                            TagsInput::make('notes')->label('Notes')->suggestions(fn() => getSettingOptions('consultation_notes'))->separator(',')->placeholder('Type and press comma to add'),
                                            TagsInput::make('advice')->label('Advice')->suggestions(fn() => getSettingOptions('consultation_advice'))->separator(',')->placeholder('Type and press comma to add'),
                                        ]),
                                        DatePicker::make('followup_date')->label('Followup Date'),
                                    ]),
                                Tabs\Tab::make('Treatment')
                                    ->schema([
                                        Repeater::make('prescription_list')
                                            ->label('Treatment')
                                            ->addActionLabel('Add Medicine')
                                            ->columns(5)
                                            ->schema([
                                                Select::make('type')
                                                    ->label('Type')
                                                    ->options([
                                                        'Capsule' => 'Capsule',
                                                        'Candy' => 'Candy',
                                                        'Tablet' => 'Tablet',
                                                        'Eye Drop' => 'Eye Drop',
                                                        'Ointment' => 'Ointment',
                                                        'Syrup' => 'Syrup',
                                                        'Injection' => 'Injection',
                                                    ]),
                                                TextInput::make('medicine')
                                                    ->label('Medicine Name')
                                                    ->datalist(fn() => getSettingOptions('medicines')),
                                                Select::make('frequency')
                                                    ->label('Frequency')
                                                    ->options([
                                                        'Hourly/हर घंटे' => 'Hourly/हर घंटे',
                                                        'Once A Day/दिन में एक बार' => 'Once A Day/दिन में एक बार',
                                                        'Twice A Day/दिन में दो बार' => 'Twice A Day/दिन में दो बार',
                                                        'Thrice A Day/दिन में तीन बार' => 'Thrice A Day/दिन में तीन बार',
                                                        'Four Times A Day/दिन में चार बार' => 'Four Times A Day/दिन में चार बार',
                                                        'Six Times A Day/दिन में छ: बार' => 'Six Times A Day/दिन में छ: बार',
                                                        'Once A Week/हफ्ते में एक बार' => 'Once A Week/हफ्ते में एक बार',
                                                        'Twice A Week/हफ्ते में दो बार' => 'Twice A Week/हफ्ते में दो बार',
                                                        'Alternate Days/एक दिन छोड़कर' => 'Alternate Days/एक दिन छोड़कर',
                                                    ]),
                                                Select::make('time')
                                                    ->label('Time')
                                                    ->options([
                                                        'Morning,Evening/सुबह,शाम' => 'Morning,Evening/सुबह,शाम',
                                                        'Morning,Afternoon,Evening/सुबह,दोपहर,शाम' => 'Morning,Afternoon,Evening/सुबह,दोपहर,शाम',
                                                        'Empty Stomach/खाली पेट' => 'Empty Stomach/खाली पेट',
                                                        'At Night/रात में' => 'At Night/रात में',
                                                        'At Noon/दोपहर में' => 'At Noon/दोपहर में',
                                                        'At Morning/सुबह' => 'At Morning/सुबह',
                                                        'Before Meal/भोजन से पहले' => 'Before Meal/भोजन से पहले',
                                                        'After Meal/भोजन के बाद' => 'After Meal/भोजन के बाद',
                                                        'SOS/जब आवश्यकता हो' => 'SOS/जब आवश्यकता हो',
                                                        'On Physician Advice/डॉक्टर की सलाह पर' => 'On Physician Advice/डॉक्टर की सलाह पर',
                                                    ]),
                                                Select::make('duration')
                                                    ->label('Duration')
                                                    ->options(function () {
                                                        $opts = [];
                                                        for ($i = 1; $i <= 30; $i++) { $opts["$i Days"] = "$i Days"; }
                                                        $opts["60 Days"] = "60 Days";
                                                        $opts["90 Days"] = "90 Days";
                                                        return $opts;
                                                    }),
                                            ])
                                    ]),
                                Tabs\Tab::make('Optometrist')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            \Filament\Forms\Components\TextInput::make('opt_optometrist_name')
                                                ->label('Name of Optometrist'),
                                            \Filament\Forms\Components\TextInput::make('opt_optometrist_id_no')
                                                ->label('Optometrist ID'),
                                        ]),

                                        // ── Visual Acuity ──────────────────────────────────────────────
                                        Section::make('Visual Acuity')
                                            ->schema([
                                                \Filament\Forms\Components\Placeholder::make('va_table')
                                                    ->hiddenLabel()
                                                    ->content(new \Illuminate\Support\HtmlString('
<style>.opt-tbl{width:100%;border-collapse:collapse;font-size:13px}.opt-tbl th,.opt-tbl td{border:1px solid #374151;padding:4px 6px;text-align:center}.opt-tbl th{background:#1f2937;color:#d1d5db;font-weight:600}.opt-tbl td:first-child{font-weight:700;width:50px;text-align:center}.opt-inp{width:100%;border:none;background:transparent;color:inherit;text-align:center;outline:none;font-size:13px;padding:2px}</style>
<table class="opt-tbl">
<thead><tr><th>Eye</th><th>Unaided</th><th>With Glass</th><th>Near</th><th>PG Sph</th><th>PG Cyl</th><th>PG Axis</th></tr></thead>
<tbody>
<tr><td>RE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_re_unaided" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_re_with_glass" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_re_near" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_re_pg_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_re_pg_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_re_pg_axis" placeholder="-"></td></tr>
<tr><td>LE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_le_unaided" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_le_with_glass" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_le_near" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_le_pg_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_le_pg_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_va_le_pg_axis" placeholder="-"></td></tr>
</tbody></table>
                                                    ')),
                                                \Filament\Forms\Components\Hidden::make('opt_va_re_unaided'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_re_with_glass'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_re_near'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_re_pg_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_re_pg_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_re_pg_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_le_unaided'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_le_with_glass'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_le_near'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_le_pg_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_le_pg_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_va_le_pg_axis'),
                                            ]),

                                        // ── Dry Retinoscopy ────────────────────────────────────────────
                                        Section::make('Dry Retinoscopy — Dry Acceptance')
                                            ->schema([
                                                \Filament\Forms\Components\Placeholder::make('dry_table')
                                                    ->hiddenLabel()
                                                    ->content(new \Illuminate\Support\HtmlString('
<table class="opt-tbl">
<thead><tr><th>Eye</th><th>Sph</th><th>Cyl</th><th>Axis</th><th>Vision</th></tr></thead>
<tbody>
<tr><td>RE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_re_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_re_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_re_axis" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_re_vision" placeholder="-"></td></tr>
<tr><td>LE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_le_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_le_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_le_axis" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_dry_le_vision" placeholder="-"></td></tr>
</tbody></table>
                                                    ')),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_re_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_re_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_re_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_re_vision'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_le_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_le_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_le_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_dry_le_vision'),
                                                Grid::make(2)->schema([
                                                    \Filament\Forms\Components\TextInput::make('opt_dry_remark')->label('Remark'),
                                                    \Filament\Forms\Components\TextInput::make('opt_dry_dd')->label('DD'),
                                                ]),
                                            ]),

                                        // ── Wet Retinoscopy ────────────────────────────────────────────
                                        Section::make('Wet Retinoscopy')
                                            ->schema([
                                                \Filament\Forms\Components\Placeholder::make('wet_table')
                                                    ->hiddenLabel()
                                                    ->content(new \Illuminate\Support\HtmlString('
<table class="opt-tbl">
<thead><tr><th>Eye</th><th>Sph</th><th>Cyl</th><th>Axis</th><th>Vision</th></tr></thead>
<tbody>
<tr><td>RE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_re_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_re_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_re_axis" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_re_vision" placeholder="-"></td></tr>
<tr><td>LE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_le_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_le_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_le_axis" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_wet_le_vision" placeholder="-"></td></tr>
</tbody></table>
                                                    ')),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_re_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_re_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_re_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_re_vision'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_le_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_le_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_le_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_wet_le_vision'),
                                            ]),

                                        // ── Final Glass Prescription ───────────────────────────────────
                                        Section::make('Final Glass Prescription')
                                            ->schema([
                                                \Filament\Forms\Components\Placeholder::make('fp_table')
                                                    ->hiddenLabel()
                                                    ->content(new \Illuminate\Support\HtmlString('
<table class="opt-tbl">
<thead><tr><th>Eye</th><th>SPH</th><th>CYL</th><th>AXIS</th><th>BCVA</th><th>Near Add</th></tr></thead>
<tbody>
<tr><td>RE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_re_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_re_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_re_axis" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_re_bcva" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_re_near_add" placeholder="-"></td></tr>
<tr><td>LE</td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_le_sph" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_le_cyl" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_le_axis" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_le_bcva" placeholder="-"></td><td><input class="opt-inp" wire:model.blur="mountedTableActionsData.0.opt_fp_le_near_add" placeholder="-"></td></tr>
</tbody></table>
                                                    ')),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_re_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_re_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_re_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_re_bcva'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_re_near_add'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_le_sph'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_le_cyl'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_le_axis'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_le_bcva'),
                                                \Filament\Forms\Components\Hidden::make('opt_fp_le_near_add'),
                                                Grid::make(2)->schema([
                                                    \Filament\Forms\Components\TextInput::make('opt_fp_remark')->label('Remark'),
                                                    \Filament\Forms\Components\TextInput::make('opt_fp_amount_glass')->label('Amount of Glass'),
                                                ]),
                                            ]),
                                    ]),
                                Tabs\Tab::make('In Patient')
                                    ->schema((function () {
                                        $ipdVersion = (int) env('IPD_VERSION', 1);
                                        if ($ipdVersion === 2) {
                                            // ── V2 : Structured Discharge Sheet ────────────────────────────
                                            return [
                                                \Filament\Forms\Components\Placeholder::make('ipd2_id_badge')
                                                    ->hiddenLabel()
                                                    ->content(fn (?Appointment $record) => new \Illuminate\Support\HtmlString(
                                                        '<strong>In-Patient ID (V2):</strong> ' .
                                                        ($record && IpdV2::where('appointment_id', $record->id)->exists()
                                                            ? IpdV2::where('appointment_id', $record->id)->value('id')
                                                            : 'Save to generate ID')
                                                    ))
                                                    ->extraAttributes(['class' => 'font-bold p-3 border border-primary-500 rounded text-primary-600 bg-primary-50 dark:bg-gray-800'])
                                                    ->hintActions([
                                                        \Filament\Actions\Action::make('print_discharge')
                                                            ->icon('heroicon-o-printer')
                                                            ->label('Print Discharge')
                                                            ->action(function (?Appointment $record, $livewire) {
                                                                if ($record) {
                                                                    $url = url("/print/ipd/discharge/{$record->patient_id}/{$record->id}");
                                                                    $livewire->js("window.open('{$url}', '_blank');");
                                                                }
                                                            })
                                                            ->visible(fn (?Appointment $record) => $record && IpdV2::where('appointment_id', $record->id)->exists()),
                                                    ]),

                                                // ── Admission / Surgery / Discharge dates ──
                                                Grid::make(3)->schema([
                                                    DatePicker::make('ipd2_date_of_admission')->label('Date of Admission'),
                                                    DatePicker::make('ipd2_date_of_surgery')->label('Date of Surgery'),
                                                    DatePicker::make('ipd2_date_of_discharge')->label('Date of Discharge'),
                                                ]),

                                                // ── Clinical info ──
                                                TextInput::make('ipd2_final_diagnosis')->label('Final Diagnosis')->columnSpanFull(),
                                                Textarea::make('ipd2_procedure_surgery')->label('Procedure / Surgery')->rows(2)->columnSpanFull(),
                                                TextInput::make('ipd2_surgeon_name')->label("Surgeon's Name"),
                                                TextInput::make('ipd2_investigation')->label('Investigation During Hospitalization'),
                                                TextInput::make('ipd2_condition_on_discharge')->label('Condition on Discharge')->columnSpanFull(),

                                                // ── Follow-up & instructions ──
                                                Grid::make(2)->schema([
                                                    DatePicker::make('ipd2_next_followup_date')->label('Next Follow-up Date'),
                                                    TextInput::make('ipd2_post_operative_rest')->label('Post Operative Rest'),
                                                ]),
                                                Textarea::make('ipd2_special_instruction')->label('Special Instruction')->rows(2)->columnSpanFull(),

                                                // ── Medication (same repeater as Treatment tab) ──
                                                \Filament\Schemas\Components\Section::make('Medicine / Rx')
                                                    ->schema([
                                                        Repeater::make('ipd2_prescription_list')
                                                            ->label('')
                                                            ->addActionLabel('Add Medicine')
                                                            ->columns(5)
                                                            ->schema([
                                                                Select::make('type')
                                                                    ->label('Type')
                                                                    ->options([
                                                                        'Capsule'   => 'Capsule',
                                                                        'Candy'     => 'Candy',
                                                                        'Tablet'    => 'Tablet',
                                                                        'Eye Drop'  => 'Eye Drop',
                                                                        'Ointment'  => 'Ointment',
                                                                        'Syrup'     => 'Syrup',
                                                                        'Injection' => 'Injection',
                                                                    ]),
                                                                TextInput::make('medicine')
                                                                    ->label('Medicine Name')
                                                                    ->datalist(fn() => getSettingOptions('medicines')),
                                                                Select::make('frequency')
                                                                    ->label('Frequency')
                                                                    ->options([
                                                                        'Hourly/हर घंटे'                              => 'Hourly/हर घंटे',
                                                                        'Once A Day/दिन में एक बार'                   => 'Once A Day/दिन में एक बार',
                                                                        'Twice A Day/दिन में दो बार'                  => 'Twice A Day/दिन में दो बार',
                                                                        'Thrice A Day/दिन में तीन बार'                => 'Thrice A Day/दिन में तीन बार',
                                                                        'Four Times A Day/दिन में चार बार'            => 'Four Times A Day/दिन में चार बार',
                                                                        'Six Times A Day/दिन में छ: बार'              => 'Six Times A Day/दिन में छ: बार',
                                                                        'Once A Week/हफ्ते में एक बार'                => 'Once A Week/हफ्ते में एक बार',
                                                                        'Twice A Week/हफ्ते में दो बार'               => 'Twice A Week/हफ्ते में दो बार',
                                                                        'Alternate Days/एक दिन छोड़कर'                => 'Alternate Days/एक दिन छोड़कर',
                                                                    ]),
                                                                Select::make('time')
                                                                    ->label('Time')
                                                                    ->options([
                                                                        'Morning,Evening/सुबह,शाम'                               => 'Morning,Evening/सुबह,शाम',
                                                                        'Morning,Afternoon,Evening/सुबह,दोपहर,शाम'               => 'Morning,Afternoon,Evening/सुबह,दोपहर,शाम',
                                                                        'Empty Stomach/खाली पेट'                                 => 'Empty Stomach/खाली पेट',
                                                                        'At Night/रात में'                                       => 'At Night/रात में',
                                                                        'At Noon/दोपहर में'                                      => 'At Noon/दोपहर में',
                                                                        'At Morning/सुबह'                                        => 'At Morning/सुबह',
                                                                        'Before Meal/भोजन से पहले'                               => 'Before Meal/भोजन से पहले',
                                                                        'After Meal/भोजन के बाद'                                 => 'After Meal/भोजन के बाद',
                                                                        'SOS/जब आवश्यकता हो'                                     => 'SOS/जब आवश्यकता हो',
                                                                        'On Physician Advice/डॉक्टर की सलाह पर'                 => 'On Physician Advice/डॉक्टर की सलाह पर',
                                                                    ]),
                                                                Select::make('duration')
                                                                    ->label('Duration')
                                                                    ->options(function () {
                                                                        $opts = [];
                                                                        for ($i = 1; $i <= 30; $i++) { $opts["$i Days"] = "$i Days"; }
                                                                        $opts["60 Days"] = "60 Days";
                                                                        $opts["90 Days"] = "90 Days";
                                                                        return $opts;
                                                                    }),
                                                            ]),
                                                    ]),

                                                // ── Instruction (cut-paste / free text) ──
                                                Textarea::make('ipd2_instruction')->label('Instruction')->rows(2)->columnSpanFull(),
                                            ];
                                        }

                                        // ── V1 : Legacy Rich-Editor (original behaviour) ────────────────
                                        return [
                                            \Filament\Forms\Components\Placeholder::make('ipd_id')
                                                ->hiddenLabel()
                                                ->content(fn (?Appointment $record) => new \Illuminate\Support\HtmlString('<strong>In-Patient ID:</strong> ' . ($record && IpdV1::where('appointment_id', $record->id)->exists()
                                                    ? IpdV1::where('appointment_id', $record->id)->value('id')
                                                    : 'Please save details to generate ID')))
                                                ->extraAttributes(['class' => 'font-bold p-3 border border-primary-500 rounded text-primary-600 bg-primary-50 dark:bg-gray-800']),
                                            \Filament\Forms\Components\RichEditor::make('inpatient_details')
                                                ->label('In-Patient Details')
                                                ->formatStateUsing(function ($state) {
                                                    if (!is_string($state) || empty($state)) return $state;
                                                    if (str_contains($state, 'Ã') || str_contains($state, 'â€')) {
                                                        $converted = @mb_convert_encoding($state, 'UTF-8', 'ISO-8859-1');
                                                        if (is_string($converted) && !empty($converted)) return $converted;
                                                    }
                                                    return mb_convert_encoding($state, 'UTF-8', 'UTF-8');
                                                })
                                                ->hintActions([
                                                    \Filament\Actions\Action::make('print_direct')
                                                        ->icon('heroicon-o-printer')
                                                        ->label('Print')
                                                        ->modalHeading('Printing...')
                                                        ->modalSubmitAction(false)
                                                        ->modalCancelAction(false)
                                                        ->modalContent(fn (\Filament\Schemas\Components\Utilities\Get $get) => new \Illuminate\Support\HtmlString('
                                                            <div id="quick-print-content" class="hidden">' . ($get('inpatient_details') ?? ' ') . '</div>
                                                            <div class="text-center p-4 font-semibold text-lg text-primary-600" x-data="{ init() {
                                                                setTimeout(function() {
                                                                    var pwin = window.open(\'\', \'_blank\', \'height=600,width=800\');
                                                                    pwin.document.write(\'<html><head><title>Print In-Patient Details</title><style>body{font-family:sans-serif;padding:20px;}</style></head><body>\');
                                                                    pwin.document.write(document.getElementById(\'quick-print-content\').innerHTML);
                                                                    pwin.document.write(\'</body></html>\');
                                                                    pwin.document.close(); pwin.focus();
                                                                    setTimeout(function() { pwin.print(); pwin.close(); let c = document.querySelectorAll(\'.fi-modal-close-btn\'); if(c.length>0) c[c.length-1].click(); }, 500);
                                                                }, 100);
                                                            } }">Generating print layout...</div>
                                                        ')),
                                                    \Filament\Actions\Action::make('preview')
                                                        ->icon('heroicon-o-eye')
                                                        ->label('Preview')
                                                        ->modalHeading('In-Patient Details Preview')
                                                        ->modalSubmitAction(false)
                                                        ->modalContent(fn (\Filament\Schemas\Components\Utilities\Get $get) => new \Illuminate\Support\HtmlString('
                                                            <div id="preview-ipd-content" class="prose dark:prose-invert max-w-none border p-4 rounded bg-white dark:bg-gray-900 text-black dark:text-white">' . ($get('inpatient_details') ?? '<em>Nothing to preview yet.</em>') . '</div>
                                                        ')),
                                                ]),
                                        ];
                                    })()),
                            ])
                            ->columnSpanFull()
                    ])
                    ->modalSubmitActionLabel('Save & Close')
                    ->modalFooterActions(function (Action $action): array {
                        return array_filter([
                            $action->getModalSubmitAction(),
                            Action::make('save_next')
                                ->label('Save & Next')
                                ->color('gray')
                                ->close(false)
                                ->action(function (Appointment $record, $livewire) use ($action): void {
                                    $schemaState = $livewire->mountedActions[$action->getNestingIndex()]['data'] ?? [];

                                    self::persistConsultation($record, $schemaState);
                                    $livewire->js("(function(){const ws=document.querySelectorAll('.fi-modal-window');const w=ws[ws.length-1];if(!w)return;const tabs=Array.from(w.querySelectorAll('.fi-tabs-item[data-tab-key]'));if(!tabs.length)return;const i=tabs.findIndex(t=>t.classList.contains('fi-active')||t.getAttribute('aria-selected')==='true');const n=tabs[i+1];if(n){n.click();}})();");
                                })
                                ->successNotificationTitle('Consultation saved successfully'),
                            $action->getModalCancelAction(),
                            Action::make('print')
                                ->label('Print')
                                ->icon('heroicon-o-printer')
                                ->color('gray')
                                ->extraAttributes(['class' => 'ms-auto'])
                                ->form([
                                    CheckboxList::make('print_options')
                                        ->label('')
                                        ->options([
                                            'patientinfo' => 'Patient Details',
                                            'diagnosis' => 'Diagnosis',
                                            'treatment' => 'Treatment',
                                            'eyedetails' => 'Eye Details',
                                            'disablebanner' => 'Enable Top Banner'
                                        ])
                                        ->default(['patientinfo'])
                                        ->columns(5)
                                        ->gridDirection('row'),
                                ])
                                ->modalWidth('4xl')
                                ->modalHeading('Select Print Options')
                                ->modalSubmitActionLabel('Print')
                                ->action(function (Appointment $record, array $data, $livewire): void {
                                    $selectedOptions = implode(':', $data['print_options'] ?? []) . ':';
                                    $url = url("/print/patient/details/{$record->patient_id}/{$record->id}?option={$selectedOptions}");
                                    $livewire->js("window.open('{$url}', '_blank');");
                                }),
                        ]);
                    })
                    ->action(function (Appointment $record, array $data): void {
                        self::persistConsultation($record, $data);
                    })
                    ->modalWidth('7xl')
                    ->modalHeading('Start Consultation')
                    ->successNotificationTitle('Consultation saved successfully'),
                Action::make('payment')
                    ->label('Pay')
                    ->icon('heroicon-o-currency-rupee')
                    ->color('warning')
                    ->form([
                        TextInput::make('patient_name')
                            ->label('Patient Name')
                            ->default(fn(Appointment $record) => $record->patient->name)
                            ->disabled(),
                        Select::make('description')
                            ->options([
                                'CONSULTATION' => 'CONSULTATION',
                                'REGISTRATION' => 'REGISTRATION',
                                'INVESTIGATION' => 'INVESTIGATION',
                                'PROCEDURE' => 'PROCEDURE',
                                'SURGERY' => 'SURGERY',
                                'OTHER' => 'OTHER',
                            ])
                            ->required()
                            ->default('CONSULTATION'),
                        Select::make('mode')
                            ->label('Payment Mode')
                            ->options([
                                'Cash' => 'Cash',
                                'UPI' => 'UPI',
                                'Card' => 'Card',
                                'Cheque' => 'Cheque',
                                'Bank Transfer' => 'Bank Transfer',
                            ])
                            ->required()
                            ->default('Cash'),
                        TextInput::make('transaction_id')
                            ->label('Txn Id/Cheque No'),
                        TextInput::make('amount')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        Payment::create([
                            'patient_id' => $record->patient_id,
                            'appointment_id' => $record->id,
                            'amount' => $data['amount'],
                            'mode' => $data['mode'],
                            'description' => $data['description'],
                            'transaction_id' => $data['transaction_id'],
                        ]);
                    })
                    ->successNotificationTitle('Payment recorded successfully'),
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn(Appointment $record): string => url("/print/patient/history/{$record->patient_id}"))
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
