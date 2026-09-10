<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptometristWorksheet extends Model
{
    protected $table = 'optometrist_worksheets';

    protected $fillable = [
        'patient_id', 'appointment_id',
        'optometrist_name', 'optometrist_id_no',
        // Visual Acuity
        'va_re_unaided', 'va_re_with_glass', 'va_re_near',
        'va_re_pg_sph', 'va_re_pg_cyl', 'va_re_pg_axis',
        'va_le_unaided', 'va_le_with_glass', 'va_le_near',
        'va_le_pg_sph', 'va_le_pg_cyl', 'va_le_pg_axis',
        // Dry Retinoscopy
        'dry_re_sph', 'dry_re_cyl', 'dry_re_axis', 'dry_re_vision',
        'dry_le_sph', 'dry_le_cyl', 'dry_le_axis', 'dry_le_vision',
        'dry_remark', 'dry_dd',
        // Wet Retinoscopy
        'wet_re_sph', 'wet_re_cyl', 'wet_re_axis', 'wet_re_vision',
        'wet_le_sph', 'wet_le_cyl', 'wet_le_axis', 'wet_le_vision',
        // Final Glass Prescription
        'fp_re_sph', 'fp_re_cyl', 'fp_re_axis', 'fp_re_bcva', 'fp_re_near_add',
        'fp_le_sph', 'fp_le_cyl', 'fp_le_axis', 'fp_le_bcva', 'fp_le_near_add',
        'fp_remark', 'fp_amount_glass',
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
