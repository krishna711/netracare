<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'age',
        'sex',
        'address',
        'occuption',
        'abha_number',
        'abha_address',
        'abdm_status',
        'abdm_profile',
        'abdm_verified_at',
    ];

    protected $casts = [
        'abdm_profile' => 'array',
        'abdm_verified_at' => 'datetime',
    ];

    public function isAbhaVerified(): bool
    {
        return !empty($this->abha_number) && in_array($this->abdm_status, ['verified', 'linked', 'created']);
    }

    public function getFormattedAbhaNumberAttribute(): ?string
    {
        if (empty($this->abha_number)) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $this->abha_number);
        if (strlen($digits) === 14) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4) . '-' . substr($digits, 10, 4);
        }

        return $this->abha_number;
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
