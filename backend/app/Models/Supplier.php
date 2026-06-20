<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'province',
        'city',
        'district',
        'address',
        'business_license',
        'bank_name',
        'bank_account',
        'tax_number',
        'remark',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const STATUS_ACTIVE = true;
    const STATUS_INACTIVE = false;

    public static function getStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_ACTIVE, 'label' => '启用'],
            ['value' => self::STATUS_INACTIVE, 'label' => '禁用'],
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(MoqOrder::class);
    }

    public function getFullAddressAttribute()
    {
        return implode('', array_filter([
            $this->province,
            $this->city,
            $this->district,
            $this->address,
        ]));
    }
}
