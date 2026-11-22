<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Area extends Model
{
    protected $fillable = ['emirate_id', 'name_ar', 'name_en', 'lat', 'lng', 'active'];

    protected $casts = ['active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];

    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Emirate::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('delivery_fee', 'min_order');
    }

    /** Nearest active area to a point (haversine, computed in PHP so it works on SQLite and MySQL). */
    public static function nearest(float $lat, float $lng): ?self
    {
        return static::query()
            ->where('active', true)
            ->whereNotNull('lat')
            ->whereHas('emirate', fn (Builder $q) => $q->where('active', true))
            ->get()
            ->sortBy(fn (self $a) => self::distanceKm($lat, $lng, $a->lat, $a->lng))
            ->first();
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($a));
    }
}
