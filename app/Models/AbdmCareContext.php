<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbdmCareContext extends Model
{
    protected $table = 'abdm_care_contexts';

    protected $fillable = [
        'patient_id',
        'care_context_reference',
        'display_name',
        'hi_type',
        'appointment_id',
        'consultation_id',
        'patient_reference',
        'status',
        'linked_at',
        'link_token',
        'metadata',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function isLinked(): bool
    {
        return $this->status === 'linked';
    }

    public function scopeLinked(Builder $query): Builder
    {
        return $query->where('status', 'linked');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', '!=', 'linked');
    }
}
