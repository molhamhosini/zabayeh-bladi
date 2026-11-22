<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverySlot extends Model
{
    protected $fillable = ['branch_id', 'date', 'starts_at', 'ends_at', 'capacity', 'booked'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isFull(): bool
    {
        return $this->booked >= $this->capacity;
    }
}
