<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'logo', 'is_own', 'commission_rate', 'trade_license_no', 'contact_phone', 'active'];

    protected $casts = ['is_own' => 'boolean', 'active' => 'boolean', 'commission_rate' => 'decimal:2'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
