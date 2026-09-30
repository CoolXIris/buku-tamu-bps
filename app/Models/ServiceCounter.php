<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCounter extends Model
{
    protected $primaryKey = 'number';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['number', 'visitor_entry_id'];

    public function visitorEntry(): BelongsTo
    {
        return $this->belongsTo(VisitorEntry::class);
    }
}