<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoqOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'moq_order_id',
        'product_id',
        'product_name',
        'product_sku',
        'specification',
        'quantity',
        'unit_price',
        'total_price',
        'cost_price',
        'shipped_quantity',
        'remark',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'shipped_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(MoqOrder::class, 'moq_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getUnshippedQuantityAttribute()
    {
        return max(0, $this->quantity - $this->shipped_quantity);
    }

    public function getIsFullyShippedAttribute()
    {
        return $this->shipped_quantity >= $this->quantity;
    }
}
