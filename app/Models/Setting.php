<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    
    protected $guarded = [];
    
    public $timestamps = false;

    protected $attributes = [
        'field' => '{"name":"value","type":"text","title":"Value"}',
        'active' => 1,
        'description' => '',
    ];
}
