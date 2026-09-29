<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorEntry extends Model
{
    protected $fillable = [
        'queue_no',
        'service_code',
        'queue_number',
        'full_name',
        'gender',
        'institution',
        'phone',
        'email',
        'occupation',
        'occupation_other',
        'purpose',
        'purpose_other',
        'service_status',
    ];
}
