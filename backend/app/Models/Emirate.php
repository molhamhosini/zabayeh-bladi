<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Emirate extends Model
{
    protected $fillable = ['slug', 'name_ar', 'name_en', 'active', 'sort'];

    protected $casts = ['active' => 'boolean'];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
