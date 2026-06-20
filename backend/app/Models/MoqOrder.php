<?php

namespace App\Models;

use App\Exceptions\Moq\InvalidStatusTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoqOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_no',
        'supplier_id',
        'customer_name',
        'customer_phone',
        'province',
        'city',
        'district',
        'address',
        'address_detail',
        'total_amount',
        'shipping_fee',
        'discount_amount',
        'payable_amount',
        'paid_amount',
        'payment_method',
        'paid_at',
        'status',
        'source',
        'remark',
        'internal_note',
        'confirmed_at',
        'shipped_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'full_address',
        'total_quantity',
        'shipped_quantity',
        'unpaid_amount',
        'is_fully_shipped',
        'is_fully_paid',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    const SOURCE_MANUAL = 'manual';
    const SOURCE_API = 'api';
    const SOURCE_IMPORT = 'import';

    const PAYMENT_WECHAT = 'wechat';
    const PAYMENT_ALIPAY = 'alipay';
    const PAYMENT_BANK = 'bank';
    const PAYMENT_CASH = 'cash';
    const PAYMENT_CREDIT = 'credit';

    public static function getStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_PENDING, 'label' => '待确认', 'color' => 'warning'],
            ['value' => self::STATUS_CONFIRMED, 'label' => '已确认', 'color' => 'primary'],
            ['value' => self::STATUS_PROCESSING, 'label' => '处理中', 'color' => 'info'],
            ['value' => self::STATUS_SHIPPED, 'label' => '已发货', 'color' => 'success'],
            ['value' => self::STATUS_COMPLETED, 'label' => '已完成', 'color' => 'success'],
            ['value' => self::STATUS_CANCELLED, 'label' => '已取消', 'color' => 'danger'],
            ['value' => self::STATUS_REFUNDED, 'label' => '已退款', 'color' => 'danger'],
        ];
    }

    public static function getSourceOptions(): array
    {
        return [
            ['value' => self::SOURCE_MANUAL, 'label' => '手工创建'],
            ['value' => self::SOURCE_API, 'label' => 'API对接'],
            ['value' => self::SOURCE_IMPORT, 'label' => '批量导入'],
        ];
    }

    public static function getPaymentOptions(): array
    {
        return [
            ['value' => self::PAYMENT_WECHAT, 'label' => '微信支付'],
            ['value' => self::PAYMENT_ALIPAY, 'label' => '支付宝'],
            ['value' => self::PAYMENT_BANK, 'label' => '银行转账'],
            ['value' => self::PAYMENT_CASH, 'label' => '现金'],
            ['value' => self::PAYMENT_CREDIT, 'label' => '赊账'],
        ];
    }

    const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_COMPLETED, self::STATUS_REFUNDED, self::STATUS_SHIPPED],
        self::STATUS_COMPLETED => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => [],
    ];

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function assertCanTransitionTo(string $target): void
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStatusTransitionException::for($this->status, $target);
        }
    }

    public function isShippable(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_PROCESSING, self::STATUS_SHIPPED], true);
    }

    public function isPayable(): bool
    {
        return !in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REFUNDED], true);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_COMPLETED], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PROCESSING], true);
    }

    public function isCompletable(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(MoqOrderItem::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
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

    public function getFullAddressAttribute()
    {
        return implode('', array_filter([
            $this->province,
            $this->city,
            $this->district,
            $this->address,
        ]));
    }

    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getShippedQuantityAttribute()
    {
        return $this->items->sum('shipped_quantity');
    }

    public function getUnpaidAmountAttribute()
    {
        return max(0, $this->payable_amount - $this->paid_amount);
    }

    public function getIsFullyShippedAttribute()
    {
        return $this->shipped_quantity >= $this->total_quantity;
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->paid_amount >= $this->payable_amount;
    }
}
