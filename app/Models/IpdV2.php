<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpdV2 extends Model
{
    protected $table = 'ipds_v2';

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'date_of_admission',
        'date_of_surgery',
        'date_of_discharge',
        'final_diagnosis',
        'procedure_surgery',
        'surgeon_name',
        'investigation_during_hospitalization',
        'condition_on_discharge',
        'next_followup_date',
        'post_operative_rest',
        'special_instruction',
        'prescription',
        'instruction',
    ];

    protected $casts = [
        'date_of_admission'  => 'date',
        'date_of_surgery'    => 'date',
        'date_of_discharge'  => 'date',
        'next_followup_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Parse the stored prescription string into an array of rows.
     * Format: type | medicine | frequency | time | duration ~ ...
     */
    public function getPrescriptionListAttribute(): array
    {
        if (empty($this->prescription)) {
            return [];
        }
        $lines = array_filter(explode('~', $this->prescription));
        $list = [];
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            $list[] = [
                'type'      => $parts[0] ?? '',
                'medicine'  => $parts[1] ?? '',
                'frequency' => $parts[2] ?? '',
                'time'      => $parts[3] ?? '',
                'duration'  => $parts[4] ?? '',
            ];
        }
        return $list;
    }
}
