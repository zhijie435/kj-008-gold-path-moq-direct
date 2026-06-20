<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_no',
        'moq_order_id',
        'carrier_code',
        'carrier_name',
        'tracking_no',
        'shipping_method',
        'shipping_cost',
        'weight',
        'package_count',
        'package_info',
        'status',
        'shipped_at',
        'delivered_at',
        'tracking_data',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'package_count' => 'integer',
        'tracking_data' => 'array',
        'package_info' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'carrier_label',
        'tracking_url',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PICKED = 'picked';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_RETURNED = 'returned';

    const CARRIER_SF = 'sf';
    const CARRIER_YTO = 'yto';
    const CARRIER_ZTO = 'zto';
    const CARRIER_STO = 'sto';
    const CARRIER_BEST = 'best';
    const CARRIER_YUNDA = 'yunda';
    const CARRIER_JD = 'jd';
    const CARRIER_EMS = 'ems';

    public static function getStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_PENDING, 'label' => '待发货', 'color' => 'warning'],
            ['value' => self::STATUS_PICKED, 'label' => '已揽收', 'color' => 'primary'],
            ['value' => self::STATUS_SHIPPED, 'label' => '已发出', 'color' => 'primary'],
            ['value' => self::STATUS_IN_TRANSIT, 'label' => '运输中', 'color' => 'info'],
            ['value' => self::STATUS_DELIVERED, 'label' => '已签收', 'color' => 'success'],
            ['value' => self::STATUS_FAILED, 'label' => '派送失败', 'color' => 'danger'],
            ['value' => self::STATUS_RETURNED, 'label' => '已退回', 'color' => 'danger'],
        ];
    }

    public static function getCarrierOptions(): array
    {
        return [
            ['value' => self::CARRIER_SF, 'label' => '顺丰速运'],
            ['value' => self::CARRIER_YTO, 'label' => '圆通速递'],
            ['value' => self::CARRIER_ZTO, 'label' => '中通快递'],
            ['value' => self::CARRIER_STO, 'label' => '申通快递'],
            ['value' => self::CARRIER_BEST, 'label' => '百世快递'],
            ['value' => self::CARRIER_YUNDA, 'label' => '韵达快递'],
            ['value' => self::CARRIER_JD, 'label' => '京东物流'],
            ['value' => self::CARRIER_EMS, 'label' => 'EMS'],
        ];
    }

    public function order()
    {
        return $this->belongsTo(MoqOrder::class, 'moq_order_id');
    }

    public function getStatusLabelAttribute()
    {
        $statuses = collect(self::getStatusOptions())->pluck('label', 'value')->toArray();
        return $statuses[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = collect(self::getStatusOptions())->pluck('color', 'value')->toArray();
        return $colors[$this->status] ?? 'info';
    }

    public function getCarrierLabelAttribute()
    {
        $carriers = collect(self::getCarrierOptions())->pluck('label', 'value')->toArray();
        return $carriers[$this->carrier_code] ?? $this->carrier_name;
    }

    public function getTrackingUrlAttribute()
    {
        $trackingUrls = [
            self::CARRIER_SF => 'https://www.sf-express.com/sf-service-web/service/bill/search/routes?',
            self::CARRIER_YTO => 'https://www.yto.net.cn/api/track/query?',
            self::CARRIER_ZTO => 'https://www.zto.com/query/?',
            self::CARRIER_STO => 'https://www.sto.cn/query?',
            self::CARRIER_BEST => 'https://www.best-inc.com.cn/track/?',
            self::CARRIER_YUNDA => 'https://www.yundaex.com/cn/track?',
            self::CARRIER_JD => 'https://www.jdl.com/orderSearch?',
            self::CARRIER_EMS => 'https://www.ems.com.cn/query/?',
        ];

        if (isset($trackingUrls[$this->carrier_code])) {
            return $trackingUrls[$this->carrier_code] . http_build_query(['no' => $this->tracking_no]);
        }

        return null;
    }
}
