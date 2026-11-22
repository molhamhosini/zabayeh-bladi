<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['vendor_id', 'emirate_id', 'name_ar', 'name_en', 'phone', 'address', 'lat', 'lng', 'accepts_cod', 'active'];

    protected $casts = ['accepts_cod' => 'boolean', 'active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Emirate::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class)->withPivot('delivery_fee', 'min_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(DeliverySlot::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
