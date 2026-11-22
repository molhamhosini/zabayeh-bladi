<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'required', 'multiple', 'display', 'sort'];

    protected $casts = ['required' => 'boolean', 'multiple' => 'boolean'];

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('sort');
    }
}
