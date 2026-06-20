<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'supplier_id',
        'category',
        'brand',
        'specification',
        'unit',
        'moq',
        'price',
        'cost_price',
        'weight',
        'volume',
        'origin',
        'description',
        'images',
        'attributes',
        'stock_quantity',
        'safety_stock',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'images' => 'array',
        'attributes' => 'array',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'volume' => 'decimal:2',
        'moq' => 'integer',
        'stock_quantity' => 'integer',
        'safety_stock' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'is_low_stock',
        'profit_margin',
    ];

    const STATUS_ACTIVE = true;
    const STATUS_INACTIVE = false;

    public static function getStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_ACTIVE, 'label' => '上架'],
            ['value' => self::STATUS_INACTIVE, 'label' => '下架'],
        ];
    }

    public static function getUnitOptions(): array
    {
        return [
            ['value' => '件', 'label' => '件'],
            ['value' => '个', 'label' => '个'],
            ['value' => '套', 'label' => '套'],
            ['value' => '箱', 'label' => '箱'],
            ['value' => '盒', 'label' => '盒'],
            ['value' => '包', 'label' => '包'],
            ['value' => 'kg', 'label' => '千克(kg)'],
            ['value' => 'g', 'label' => '克(g)'],
            ['value' => 'm', 'label' => '米(m)'],
            ['value' => '㎡', 'label' => '平方米(㎡)'],
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orderItems()
    {
        return $this->hasMany(MoqOrderItem::class);
    }

    public function getIsLowStockAttribute()
    {
        return $this->stock_quantity <= $this->safety_stock;
    }

    public function getProfitMarginAttribute()
    {
        if ($this->price <= 0) return 0;
        return round(($this->price - $this->cost_price) / $this->price * 100, 2);
    }
}
