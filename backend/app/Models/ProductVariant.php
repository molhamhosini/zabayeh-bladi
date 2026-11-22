<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'weight_min_kg', 'weight_max_kg', 'age_ar', 'age_en', 'price', 'compare_at_price', 'in_stock', 'sort'];

    protected $casts = ['in_stock' => 'boolean', 'price' => 'decimal:2', 'compare_at_price' => 'decimal:2',
        'weight_min_kg' => 'float', 'weight_max_kg' => 'float'];

    protected $appends = ['label_ar', 'label_en'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    private function weight(string $unit): string
    {
        $f = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');

        return $this->weight_min_kg ? $f($this->weight_min_kg).' - '.$f($this->weight_max_kg).' '.$unit : '';
    }

    public function getLabelArAttribute(): string
    {
        return trim($this->weight('كيلو').($this->age_ar ? ' · '.$this->age_ar : ''), ' ·');
    }

    public function getLabelEnAttribute(): string
    {
        return trim($this->weight('kg').($this->age_en ? ' · '.$this->age_en : ''), ' ·');
    }
}
