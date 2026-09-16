<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbdmConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'consent_request_id',
        'consent_id',
        'status',
        'purpose_code',
        'hi_types',
        'date_from',
        'date_to',
        'data_erase_at',
        'transaction_id',
        'consent_artefact',
        'key_material',
        'transferred_records',
        'metadata',
    ];

    protected $casts = [
        'hi_types' => 'array',
        'consent_artefact' => 'array',
        'key_material' => 'array',
        'transferred_records' => 'array',
        'metadata' => 'array',
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'data_erase_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
