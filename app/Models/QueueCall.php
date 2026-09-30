<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueCall extends Model
{
    public $timestamps = false;

    protected $fillable = ['visitor_entry_id', 'counter_number'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'counter_number' => 'integer',
        ];
    }

    public function visitorEntry(): BelongsTo
    {
        return $this->belongsTo(VisitorEntry::class);
    }
}
