<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VisitorEntry extends Model
{
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('service_status', ['waiting', 'serving']);
    }

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
