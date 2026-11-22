<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $fillable = ['title_ar', 'title_en', 'subtitle_ar', 'subtitle_en', 'image', 'category_id', 'starts_on', 'ends_on', 'sort', 'active'];

    protected $casts = ['active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date'];

    protected $appends = ['image_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? Storage::disk('public')->url($this->image) : null);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('active', true)
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->orderBy('sort');
    }
}
