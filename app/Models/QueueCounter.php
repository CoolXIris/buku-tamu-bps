<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueCounter extends Model
{
    protected $table = 'queue_counters';

    protected $fillable = [
        'service_code',
        'last_number',
    ];
}
