<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['branch_id', 'category_id', 'name_ar', 'name_en', 'description_ar', 'description_en',
        'weight_min_kg', 'weight_max_kg', 'price', 'compare_at_price', 'image', 'in_stock', 'active', 'is_featured', 'sort'];

    protected $casts = ['in_stock' => 'boolean', 'active' => 'boolean', 'is_featured' => 'boolean', 'price' => 'decimal:2', 'compare_at_price' => 'decimal:2',
        'weight_min_kg' => 'float', 'weight_max_kg' => 'float'];

    protected $appends = ['image_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? Storage::disk('public')->url($this->image) : null);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('price');
    }

    /**
     * Keeps the product's own price/weight fields in step with its variants, so lists can show
     * "from" price and range without loading variants.
     */
    public function syncFromVariants(): void
    {
        $v = $this->variants()->get();
        if ($v->isEmpty()) {
            return;
        }
        $cheapest = $v->sortBy('price')->first();
        $this->forceFill([
            'price' => $cheapest->price,
            'compare_at_price' => $cheapest->compare_at_price,
            'weight_min_kg' => $v->min('weight_min_kg'),
            'weight_max_kg' => $v->max('weight_max_kg'),
            'in_stock' => $v->contains('in_stock', true),
        ])->saveQuietly();
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class)->orderBy('sort');
    }

    /** Products that are sellable and come from an active branch that delivers to the area. */
    public function scopeAvailableIn(Builder $q, int $areaId): Builder
    {
        return $q->where('active', true)
            ->whereHas('branch', fn (Builder $b) => $b->where('active', true)
                ->whereHas('vendor', fn (Builder $v) => $v->where('active', true))
                ->whereHas('areas', fn (Builder $a) => $a->where('areas.id', $areaId)));
    }
}
