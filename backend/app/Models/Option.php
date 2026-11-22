<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Option extends Model
{
    protected $fillable = ['option_group_id', 'name_ar', 'name_en', 'extra_price', 'active', 'sort'];

    protected $casts = ['active' => 'boolean', 'extra_price' => 'decimal:2'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
