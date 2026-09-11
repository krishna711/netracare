<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = ['patient_id', 'appointment_time', 'doctor_id', 'visited'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function consultation()
    {
        return $this->hasOne(Consultation::class);
    }

    public function careContext()
    {
        return $this->hasOne(AbdmCareContext::class);
    }
}
