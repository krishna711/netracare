<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbdmScanShare extends Model
{
    protected $fillable = [
        'request_id',
        'hip_id',
        'counter_id',
        'token_number',
        'abha_number',
        'abha_address',
        'name',
        'gender',
        'dob',
        'mobile',
        'address',
        'raw_profile',
        'status',
        'patient_id',
        'appointment_id',
    ];

    protected $casts = [
        'raw_profile' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
