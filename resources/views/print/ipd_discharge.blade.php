<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Discharge Sheet – {{ $patient->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Georgia, serif;
            font-size: 13px;
            color: #111;
            padding: 0 10px;
        }

        /* ── Header banner ─────────────────────────────── */
        .header-banner { width: 100%; max-width: 100%; display: block; }
        .reg-no { text-align: right; font-size: 12px; color: #333; }

        /* ── Title ─────────────────────────────────────── */
        .sheet-title {
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 10px 0 6px;
            text-decoration: underline;
        }

        /* ── Patient strip ──────────────────────────────── */
        .patient-strip {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .patient-strip .pt-name { font-weight: bold; font-size: 14px; }

        /* ── Field rows ─────────────────────────────────── */
        .field-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 7px;
            flex-wrap: wrap;
        }
        .field-label {
            font-weight: bold;
            white-space: nowrap;
            min-width: 0;
        }
        .field-value {
            border-bottom: 1px solid #555;
            flex: 1;
            min-width: 80px;
            padding-bottom: 1px;
            min-height: 16px;
        }
        .field-value.wide { flex: 2; }

        /* ── 3-column date row ──────────────────────────── */
        .date-row {
            display: flex;
            gap: 14px;
            margin-bottom: 7px;
            flex-wrap: wrap;
        }
        .date-cell { display: flex; align-items: baseline; gap: 5px; }
        .date-cell .field-value { min-width: 100px; }

        /* ── Medicine table ─────────────────────────────── */
        .section-heading {
            font-weight: bold;
            font-size: 13px;
            margin: 10px 0 4px;
            border-bottom: 1px solid #999;
            padding-bottom: 2px;
        }

        table.med {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 12px;
        }
        table.med th, table.med td {
            border: 1px solid #555;
            padding: 4px 7px;
            text-align: left;
        }
        table.med th { background: #f0f0f0; font-weight: bold; }

        /* ── Instruction block ──────────────────────────── */
        .instruction-block {
            margin-top: 8px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .instruction-block .field-value {
            min-height: 36px;
        }

        /* ── Followup + Next visit row ──────────────────── */
        .followup-row {
            display: flex;
            gap: 20px;
            align-items: baseline;
            margin-bottom: 7px;
            flex-wrap: wrap;
        }

        /* ── Divider ────────────────────────────────────── */
        .divider { border: none; border-top: 1px solid #bbb; margin: 10px 0; }

        /* ── Footer ─────────────────────────────────────── */
        footer {
            width: 98%;
            text-align: center;
            padding-top: 8px;
            overflow: hidden;
            position: fixed;
            bottom: 0;
            left: 0;
            font-size: 10px;
            border-top: 1px solid #000;
        }

        /* ── Signature row ──────────────────────────────── */
        .sig-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
            font-size: 12px;
        }
        .sig-box { text-align: center; border-top: 1px solid #555; padding-top: 4px; min-width: 180px; }

        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>

{{-- ── Banner / Header ──────────────────────────────────────── --}}
<div class="reg-no">Reg No: {{ $hospitalSettings['registration_number'] ?? '' }}</div>
@if(!empty($hospitalSettings['banner_1']))
    <img class="header-banner" src="{{ asset('storage/' . $hospitalSettings['banner_1']) }}" alt="Hospital Banner">
@else
    <img class="header-banner" src="/images/header-letter.jpg" alt="Hospital Banner">
@endif

{{-- ── Sheet Title ───────────────────────────────────────────── --}}
<div class="sheet-title">Discharge Sheet</div>

{{-- ── Patient Strip ────────────────────────────────────────── --}}
<div class="patient-strip">
    <span>
        <span class="pt-name">Pt. Name – {{ $patient->name }}</span>
        &nbsp;&nbsp;( N-{{ str_pad($patient->id, 6, '0', STR_PAD_LEFT) }} )
    </span>
    <span>Age / Sex : {{ $patient->age }} / {{ $patient->sex }}</span>
    <span>Date : <?php echo date('d-M-Y'); ?></span>
</div>

<hr class="divider">

{{-- ── Admission / Surgery / Discharge Dates ───────────────── --}}
<div class="date-row">
    <div class="date-cell">
        <span class="field-label">Date of Admission :</span>
        <span class="field-value">
            {{ $ipd && $ipd->date_of_admission ? $ipd->date_of_admission->format('d-M-Y') : '' }}
        </span>
    </div>
    <div class="date-cell">
        <span class="field-label">Date of Surgery :</span>
        <span class="field-value">
            {{ $ipd && $ipd->date_of_surgery ? $ipd->date_of_surgery->format('d-M-Y') : '' }}
        </span>
    </div>
    <div class="date-cell">
        <span class="field-label">Date of Discharge :</span>
        <span class="field-value">
            {{ $ipd && $ipd->date_of_discharge ? $ipd->date_of_discharge->format('d-M-Y') : '' }}
        </span>
    </div>
</div>

{{-- ── Final Diagnosis ──────────────────────────────────────── --}}
<div class="field-row">
    <span class="field-label">Final Diagnosis :</span>
    <span class="field-value wide">{{ $ipd->final_diagnosis ?? '' }}</span>
</div>

{{-- ── Procedure / Surgery ──────────────────────────────────── --}}
<div class="field-row">
    <span class="field-label">Procedure / Surgery :</span>
    <span class="field-value wide">{{ $ipd->procedure_surgery ?? '' }}</span>
</div>

{{-- ── Surgeon ───────────────────────────────────────────────── --}}
<div class="field-row">
    <span class="field-label">Surgeon's Name :</span>
    <span class="field-value">{{ $ipd->surgeon_name ?? ($doctor->name ?? '') }}</span>
</div>

{{-- ── Investigation ────────────────────────────────────────── --}}
<div class="field-row">
    <span class="field-label">Investigation During Hospitalization :</span>
    <span class="field-value wide">{{ $ipd->investigation_during_hospitalization ?? '' }}</span>
</div>

{{-- ── Condition on Discharge ───────────────────────────────── --}}
<div class="field-row">
    <span class="field-label">Condition on Discharge :</span>
    <span class="field-value">{{ $ipd->condition_on_discharge ?? '' }}</span>
</div>

<hr class="divider">

{{-- ── Follow-up + Post-operative Rest ────────────────────── --}}
<div class="followup-row">
    <div class="date-cell">
        <span class="field-label">Next Follow-up Date :</span>
        <span class="field-value">
            {{ $ipd && $ipd->next_followup_date ? $ipd->next_followup_date->format('d-M-Y') : '' }}
        </span>
    </div>
    <div class="date-cell">
        <span class="field-label">Post Operative Rest :</span>
        <span class="field-value">{{ $ipd->post_operative_rest ?? '' }}</span>
    </div>
</div>

{{-- ── Special Instruction ──────────────────────────────────── --}}
<div class="field-row">
    <span class="field-label">Special Instruction :</span>
    <span class="field-value wide">{{ $ipd->special_instruction ?? '' }}</span>
</div>

{{-- ── Medicine / Rx ────────────────────────────────────────── --}}
@if($ipd && !empty($ipd->prescription))
<div class="section-heading">Medicine / Rx</div>
<table class="med">
    <thead>
        <tr>
            <th>#</th>
            <th>Type</th>
            <th>Medicine</th>
            <th>Frequency</th>
            <th>Time</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
        @php
            $rxList = $ipd->prescription_list;
        @endphp
        @forelse($rxList as $i => $rx)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $rx['type'] }}</td>
            <td>{{ $rx['medicine'] }}</td>
            <td>{{ $rx['frequency'] }}</td>
            <td>{{ $rx['time'] }}</td>
            <td>{{ $rx['duration'] }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#777;">No medicines prescribed.</td></tr>
        @endforelse
    </tbody>
</table>
@else
<div class="section-heading">Medicine / Rx</div>
<table class="med">
    <thead>
        <tr>
            <th>#</th><th>Type</th><th>Medicine</th><th>Frequency</th><th>Time</th><th>Duration</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    </tbody>
</table>
@endif

{{-- ── Instruction (cut-paste / free text) ────────────────── --}}
<div class="instruction-block">
    <span class="field-label" style="white-space:nowrap;">Instruction :</span>
    <span class="field-value">{{ $ipd->instruction ?? '' }}</span>
</div>

{{-- ── Signature ─────────────────────────────────────────────── --}}
<div class="sig-row">
    <div class="sig-box">
        Doctor: {{ $doctor->name ?? '' }}<br>
        <small>Signature</small>
    </div>
</div>

{{-- ── Footer ───────────────────────────────────────────────── --}}
<footer>
    {{ $hospitalSettings['address_english'] ?? '' }}<br>
    Contact: {{ $hospitalSettings['phone_1'] ?? '' }},
             {{ $hospitalSettings['phone_2'] ?? '' }},
             {{ $hospitalSettings['phone_3'] ?? '' }}
    &nbsp;|&nbsp; Timings: {{ $hospitalSettings['timings_english'] ?? '' }}
</footer>

<script>window.print();</script>
</body>
</html>
