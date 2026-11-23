<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/** A recurring delivery time range for one emirate, e.g. Dubai 9 AM - 12 PM, up to 10 orders. */
class DeliveryWindow extends Model
{
    protected $fillable = ['emirate_id', 'starts_at', 'ends_at', 'capacity', 'active'];

    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        // Any change re-syncs upcoming slots on the next request.
        $bump = fn () => Cache::forever('delivery-windows-version', (string) microtime(true));
        static::saved($bump);
        static::deleted($bump);
    }

    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Emirate::class);
    }

    public function label(): string
    {
        return (new DeliverySlot(['starts_at' => $this->starts_at, 'ends_at' => $this->ends_at]))->label();
    }
}
