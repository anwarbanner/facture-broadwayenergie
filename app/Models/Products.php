<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Products extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'product_name',
        'designation',
        'description',
        'type',
        'category_id',
        'sale_price_ht',
        'cost_price_ht',
        'unit_type',
        'stock_quantity',
        'min_stock_alert_level',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sale_price_ht' => 'decimal:2',
        'cost_price_ht' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'min_stock_alert_level' => 'decimal:3',
    ];

    protected $appends = [
        'margin_amount',
        'margin_rate',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('type', 'product');
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_alert_level');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('reference', 'like', "%{$term}%")
                ->orWhere('product_name', 'like', "%{$term}%")
                ->orWhere('designation', 'like', "%{$term}%");
        });
    }

    public function getMarginAmountAttribute(): float
    {
        return (float) $this->sale_price_ht - (float) $this->cost_price_ht;
    }

    public function getMarginRateAttribute(): float
    {
        $cost = (float) $this->cost_price_ht;

        if ($cost <= 0) {
            return 0.0;
        }

        return round((($this->margin_amount / $cost) * 100), 2);
    }
}
