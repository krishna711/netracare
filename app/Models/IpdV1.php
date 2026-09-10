<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpdV1 extends Model
{
    protected $table = 'ipds_v1';

    protected $fillable = ['patient_id', 'appointment_id', 'details'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
