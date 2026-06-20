<?php

namespace App\Services;

use App\Exceptions\Moq\InactiveProductException;
use App\Exceptions\Moq\InsufficientMoqException;
use App\Exceptions\Moq\InsufficientStockException;
use App\Exceptions\Moq\InvalidPaymentException;
use App\Exceptions\Moq\InvalidRefundException;
use App\Exceptions\Moq\InvalidStatusTransitionException;
use App\Exceptions\Moq\MoqDirectShipException;
use App\Exceptions\Moq\ProductNotFoundException;
use App\Models\MoqOrder;
use App\Models\MoqOrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoqDirectShipService
{
    protected array $carriers = [];

    public function __construct()
    {
        $this->carriers = collect(Shipment::getCarrierOptions())
            ->pluck('label', 'value')
            ->toArray();
    }

    public function generateOrderNo(): string
    {
        $prefix = 'MOQ';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(9));
        return "{$prefix}{$date}{$random}";
    }

    public function generateShipmentNo(): string
    {
        $prefix = 'SH';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(8));
        return "{$prefix}{$date}{$random}";
    }

    public function createOrder(array $data): MoqOrder
    {
        return DB::transaction(function () use ($data) {
            $orderData = $data;
            $items = $data['items'] ?? [];
            unset($orderData['items']);

            if (empty($orderData['order_no'])) {
                $orderData['order_no'] = $this->generateOrderNo();
            }

            $this->validateMoqItems($items);

            $totals = $this->calculateOrderTotals($items);
            $orderData = array_merge($orderData, $totals);

            if (empty($orderData['payable_amount'])) {
                $orderData['payable_amount'] = $orderData['total_amount']
                    + ($orderData['shipping_fee'] ?? 0)
                    - ($orderData['discount_amount'] ?? 0);
            }

            $orderData['status'] = $orderData['status'] ?? MoqOrder::STATUS_PENDING;
            $orderData['source'] = $orderData['source'] ?? MoqOrder::SOURCE_MANUAL;

            $order = MoqOrder::create($orderData);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $orderItem = new MoqOrderItem([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'specification' => $product->specification,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? $product->price,
                    'total_price' => ($item['unit_price'] ?? $product->price) * $item['quantity'],
                    'cost_price' => $product->cost_price,
                    'remark' => $item['remark'] ?? null,
                ]);
                $order->items()->save($orderItem);
            }

            return $order->load('items', 'supplier');
        });
    }

    public function validateMoqItems(array $items): void
    {
        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                throw new MoqDirectShipException('产品ID和数量不能为空');
            }

            $product = Product::find($item['product_id']);
            if (!$product) {
                throw new ProductNotFoundException("产品不存在: {$item['product_id']}");
            }

            if (!$product->is_active) {
                throw new InactiveProductException("产品已下架: {$product->name}");
            }

            if ($item['quantity'] < $product->moq) {
                throw new InsufficientMoqException(
                    "产品 {$product->name} 最小起订量为 {$product->moq} {$product->unit}，" .
                    "当前订购 {$item['quantity']} {$product->unit}"
                );
            }

            if ($item['quantity'] > $product->stock_quantity) {
                throw new InsufficientStockException(
                    "产品 {$product->name} 库存不足，当前库存 {$product->stock_quantity} {$product->unit}"
                );
            }
        }
    }

    public function calculateOrderTotals(array $items): array
    {
        $totalAmount = 0;

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $unitPrice = $item['unit_price'] ?? ($product ? $product->price : 0);
            $totalAmount += $unitPrice * ($item['quantity'] ?? 0);
        }

        return [
            'total_amount' => $totalAmount,
        ];
    }

    public function confirmOrder(MoqOrder $order): MoqOrder
    {
        $order->assertCanTransitionTo(MoqOrder::STATUS_CONFIRMED);

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => MoqOrder::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            $this->deductStock($order);

            return $order->fresh()->load('items', 'supplier', 'shipments');
        });
    }

    public function processOrder(MoqOrder $order): MoqOrder
    {
        $order->assertCanTransitionTo(MoqOrder::STATUS_PROCESSING);

        $order->update([
            'status' => MoqOrder::STATUS_PROCESSING,
        ]);

        return $order->fresh()->load('items', 'supplier', 'shipments');
    }

    public function shipOrder(MoqOrder $order, array $shipmentData): Shipment
    {
        if (!$order->isShippable()) {
            throw InvalidStatusTransitionException::for($order->status, MoqOrder::STATUS_SHIPPED);
        }

        return DB::transaction(function () use ($order, $shipmentData) {
            $shipmentData['shipment_no'] = $shipmentData['shipment_no'] ?? $this->generateShipmentNo();
            $shipmentData['moq_order_id'] = $order->id;
            $shipmentData['status'] = $shipmentData['status'] ?? Shipment::STATUS_SHIPPED;
            $shipmentData['carrier_name'] = $shipmentData['carrier_name']
                ?? ($this->carriers[$shipmentData['carrier_code']] ?? $shipmentData['carrier_code']);

            if ($shipmentData['status'] === Shipment::STATUS_SHIPPED && empty($shipmentData['shipped_at'])) {
                $shipmentData['shipped_at'] = now();
            }

            $shipment = Shipment::create($shipmentData);

            $this->updateShippedQuantity($order, $shipmentData['items'] ?? []);

            $order->refresh();
            $targetStatus = $order->is_fully_shipped
                ? MoqOrder::STATUS_SHIPPED
                : MoqOrder::STATUS_PROCESSING;

            $order->assertCanTransitionTo($targetStatus);

            $order->update([
                'status' => $targetStatus,
                'shipped_at' => $targetStatus === MoqOrder::STATUS_SHIPPED ? now() : $order->shipped_at,
            ]);

            return $shipment->load('order');
        });
    }

    protected function updateShippedQuantity(MoqOrder $order, array $shipItems): void
    {
        foreach ($shipItems as $shipItem) {
            $orderItem = MoqOrderItem::find($shipItem['order_item_id']);
            if ($orderItem && $orderItem->moq_order_id == $order->id) {
                $newShipped = $orderItem->shipped_quantity + ($shipItem['quantity'] ?? 0);
                if ($newShipped > $orderItem->quantity) {
                    throw new MoqDirectShipException('发货数量不能超过订购数量');
                }
                $orderItem->update(['shipped_quantity' => $newShipped]);
            }
        }
    }

    protected function deductStock(MoqOrder $order): void
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $newStock = $product->stock_quantity - $item->quantity;
                if ($newStock < 0) {
                    throw new InsufficientStockException("产品 {$product->name} 库存不足");
                }
                $product->update(['stock_quantity' => $newStock]);
            }
        }
    }

    protected function restoreStock(MoqOrder $order): void
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('stock_quantity', $item->quantity - $item->shipped_quantity);
            }
        }
    }

    public function completeOrder(MoqOrder $order): MoqOrder
    {
        $order->assertCanTransitionTo(MoqOrder::STATUS_COMPLETED);

        if (!$order->is_fully_shipped) {
            throw new MoqDirectShipException('订单尚未全部发货，无法完成');
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => MoqOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            foreach ($order->shipments as $shipment) {
                if ($shipment->status !== Shipment::STATUS_DELIVERED) {
                    $shipment->update([
                        'status' => Shipment::STATUS_DELIVERED,
                        'delivered_at' => now(),
                    ]);
                }
            }
        });

        return $order->fresh()->load('items', 'supplier', 'shipments');
    }

    public function cancelOrder(MoqOrder $order, string $reason = ''): MoqOrder
    {
        $order->assertCanTransitionTo(MoqOrder::STATUS_CANCELLED);

        return DB::transaction(function () use ($order, $reason) {
            if (in_array($order->status, [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PROCESSING])) {
                $this->restoreStock($order);
            }

            $order->update([
                'status' => MoqOrder::STATUS_CANCELLED,
                'internal_note' => $order->internal_note
                    ? $order->internal_note . "\n取消原因: {$reason}"
                    : "取消原因: {$reason}",
            ]);

            return $order->fresh()->load('items', 'supplier', 'shipments');
        });
    }

    public function refundOrder(MoqOrder $order, float $amount, string $reason = ''): MoqOrder
    {
        if (!$order->isRefundable()) {
            throw new InvalidRefundException('当前订单状态不支持退款');
        }

        if ($amount <= 0 || $amount > $order->paid_amount) {
            throw new InvalidRefundException('退款金额无效');
        }

        $order->update([
            'paid_amount' => $order->paid_amount - $amount,
            'internal_note' => $order->internal_note
                ? $order->internal_note . "\n退款: {$amount}, 原因: {$reason}"
                : "退款: {$amount}, 原因: {$reason}",
        ]);

        if ($order->paid_amount <= 0 && $order->isRefundable()) {
            $order->assertCanTransitionTo(MoqOrder::STATUS_REFUNDED);
            $order->update(['status' => MoqOrder::STATUS_REFUNDED]);
        }

        return $order->fresh()->load('items', 'supplier', 'shipments');
    }

    public function payOrder(MoqOrder $order, float $amount, string $paymentMethod): MoqOrder
    {
        if (!$order->isPayable()) {
            throw new InvalidPaymentException('当前订单状态不支持支付');
        }

        $newPaidAmount = $order->paid_amount + $amount;
        if ($newPaidAmount > $order->payable_amount) {
            throw new InvalidPaymentException('支付金额超过应付金额');
        }

        $order->update([
            'paid_amount' => $newPaidAmount,
            'payment_method' => $paymentMethod,
            'paid_at' => $newPaidAmount >= $order->payable_amount ? now() : $order->paid_at,
        ]);

        return $order->fresh()->load('items', 'supplier', 'shipments');
    }

    public function updateOrder(MoqOrder $order, array $data): MoqOrder
    {
        $order->update($data);

        if (isset($data['shipping_fee']) || isset($data['discount_amount'])) {
            $totalAmount = (float) $order->items->sum('total_price');
            $shippingFee = (float) ($data['shipping_fee'] ?? $order->shipping_fee);
            $discountAmount = (float) ($data['discount_amount'] ?? $order->discount_amount);
            $order->update([
                'total_amount' => $totalAmount,
                'payable_amount' => $totalAmount + $shippingFee - $discountAmount,
            ]);
        }

        return $order->load('items', 'supplier', 'shipments');
    }

    public function deleteOrder(MoqOrder $order): void
    {
        if (!in_array($order->status, [MoqOrder::STATUS_PENDING, MoqOrder::STATUS_CANCELLED], true)) {
            throw new MoqDirectShipException('只有待确认或已取消的订单才能删除');
        }

        $order->delete();
    }

    public function getOrderList(array $params = [])
    {
        $query = MoqOrder::with(['supplier', 'items'])
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('order_no', 'like', "%{$params['keyword']}%")
                        ->orWhere('customer_name', 'like', "%{$params['keyword']}%")
                        ->orWhere('customer_phone', 'like', "%{$params['keyword']}%");
                });
            })
            ->when(!empty($params['status']), function ($q) use ($params) {
                $q->where('status', $params['status']);
            })
            ->when(!empty($params['supplier_id']), function ($q) use ($params) {
                $q->where('supplier_id', $params['supplier_id']);
            })
            ->when(!empty($params['start_date']), function ($q) use ($params) {
                $q->where('created_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($q) use ($params) {
                $q->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
            })
            ->when(!empty($params['source']), function ($q) use ($params) {
                $q->where('source', $params['source']);
            })
            ->orderBy('created_at', 'desc');

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getProductList(array $params = [])
    {
        $query = Product::with('supplier')
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('name', 'like', "%{$params['keyword']}%")
                        ->orWhere('sku', 'like', "%{$params['keyword']}%")
                        ->orWhere('barcode', 'like', "%{$params['keyword']}%");
                });
            })
            ->when(!empty($params['supplier_id']), function ($q) use ($params) {
                $q->where('supplier_id', $params['supplier_id']);
            })
            ->when(!empty($params['category']), function ($q) use ($params) {
                $q->where('category', $params['category']);
            })
            ->when(isset($params['is_active']) && $params['is_active'] !== '', function ($q) use ($params) {
                $q->where('is_active', $params['is_active']);
            })
            ->when(!empty($params['is_low_stock']), function ($q) {
                $q->whereColumn('stock_quantity', '<=', 'safety_stock');
            })
            ->when(!empty($params['moq_min']), function ($q) use ($params) {
                $q->where('moq', '>=', $params['moq_min']);
            })
            ->when(!empty($params['moq_max']), function ($q) use ($params) {
                $q->where('moq', '<=', $params['moq_max']);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getSupplierList(array $params = [])
    {
        $query = Supplier::withCount(['products', 'orders'])
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('name', 'like', "%{$params['keyword']}%")
                        ->orWhere('code', 'like', "%{$params['keyword']}%")
                        ->orWhere('contact_person', 'like', "%{$params['keyword']}%")
                        ->orWhere('phone', 'like', "%{$params['keyword']}%");
                });
            })
            ->when(isset($params['is_active']) && $params['is_active'] !== '', function ($q) use ($params) {
                $q->where('is_active', $params['is_active']);
            })
            ->when(!empty($params['province']), function ($q) use ($params) {
                $q->where('province', $params['province']);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getShipmentList(array $params = [])
    {
        $query = Shipment::with(['order' => function ($q) {
            $q->with('supplier');
        }])
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('shipment_no', 'like', "%{$params['keyword']}%")
                        ->orWhere('tracking_no', 'like', "%{$params['keyword']}%");
                });
            })
            ->when(!empty($params['status']), function ($q) use ($params) {
                $q->where('status', $params['status']);
            })
            ->when(!empty($params['carrier_code']), function ($q) use ($params) {
                $q->where('carrier_code', $params['carrier_code']);
            })
            ->when(!empty($params['moq_order_id']), function ($q) use ($params) {
                $q->where('moq_order_id', $params['moq_order_id']);
            })
            ->when(!empty($params['start_date']), function ($q) use ($params) {
                $q->where('shipped_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($q) use ($params) {
                $q->where('shipped_at', '<=', $params['end_date'] . ' 23:59:59');
            })
            ->orderBy('created_at', 'desc');

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getStatistics(array $params = []): array
    {
        $dateStart = $params['date_start'] ?? now()->startOfMonth()->toDateString();
        $dateEnd = $params['date_end'] ?? now()->endOfMonth()->toDateString();

        $orderQuery = MoqOrder::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59']);

        $totalOrders = $orderQuery->count();
        $totalAmount = $orderQuery->sum('payable_amount');
        $paidAmount = $orderQuery->sum('paid_amount');

        $statusStats = MoqOrder::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->selectRaw('status, COUNT(*) as count, SUM(payable_amount) as amount')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $productCount = Product::count();
        $activeProductCount = Product::where('is_active', true)->count();
        $lowStockCount = Product::whereRaw('stock_quantity <= safety_stock')->count();

        $supplierCount = Supplier::count();
        $activeSupplierCount = Supplier::where('is_active', true)->count();

        $shipmentCount = Shipment::whereBetween('shipped_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->count();
        $deliveredCount = Shipment::whereBetween('delivered_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->count();

        $pendingCount = $statusStats[MoqOrder::STATUS_PENDING] ?? 0;
        $confirmedCount = $statusStats[MoqOrder::STATUS_CONFIRMED] ?? 0;
        $processingCount = $statusStats[MoqOrder::STATUS_PROCESSING] ?? 0;
        $shippedCount = $statusStats[MoqOrder::STATUS_SHIPPED] ?? 0;
        $completedCount = $statusStats[MoqOrder::STATUS_COMPLETED] ?? 0;
        $cancelledCount = $statusStats[MoqOrder::STATUS_CANCELLED] ?? 0;

        return [
            'orders' => [
                'total' => $totalOrders,
                'total_amount' => round($totalAmount, 2),
                'paid_amount' => round($paidAmount, 2),
                'unpaid_amount' => round($totalAmount - $paidAmount, 2),
                'pending' => $pendingCount,
                'confirmed' => $confirmedCount,
                'processing' => $processingCount,
                'shipped' => $shippedCount,
                'completed' => $completedCount,
                'cancelled' => $cancelledCount,
            ],
            'products' => [
                'total' => $productCount,
                'active' => $activeProductCount,
                'low_stock' => $lowStockCount,
            ],
            'suppliers' => [
                'total' => $supplierCount,
                'active' => $activeSupplierCount,
            ],
            'shipments' => [
                'total' => $shipmentCount,
                'delivered' => $deliveredCount,
            ],
            'date_range' => [
                'start' => $dateStart,
                'end' => $dateEnd,
            ],
        ];
    }

    public function updateTracking(Shipment $shipment, array $trackingData): Shipment
    {
        $shipment->update([
            'tracking_data' => $trackingData,
        ]);

        if (!empty($trackingData['status'])) {
            $statusMap = [
                '已签收' => Shipment::STATUS_DELIVERED,
                '签收' => Shipment::STATUS_DELIVERED,
                '派送中' => Shipment::STATUS_IN_TRANSIT,
                '运输中' => Shipment::STATUS_IN_TRANSIT,
                '在途中' => Shipment::STATUS_IN_TRANSIT,
                '已发出' => Shipment::STATUS_SHIPPED,
                '已揽收' => Shipment::STATUS_PICKED,
                '失败' => Shipment::STATUS_FAILED,
                '退回' => Shipment::STATUS_RETURNED,
            ];

            foreach ($statusMap as $keyword => $status) {
                if (strpos($trackingData['status'], $keyword) !== false) {
                    if ($status !== $shipment->status) {
                        $shipment->assertCanTransitionTo($status);
                        $shipment->update(['status' => $status]);

                        if ($status === Shipment::STATUS_DELIVERED && empty($shipment->delivered_at)) {
                            $shipment->update(['delivered_at' => now()]);
                        }
                    }
                    break;
                }
            }
        }

        $shipment->refresh();
        $order = $shipment->order;

        if ($order && $order->status === MoqOrder::STATUS_SHIPPED) {
            $allDelivered = $order->shipments->every(function ($s) {
                return $s->status === Shipment::STATUS_DELIVERED;
            });

            if ($allDelivered) {
                $order->assertCanTransitionTo(MoqOrder::STATUS_COMPLETED);
                $order->update([
                    'status' => MoqOrder::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }
        }

        return $shipment->fresh()->load('order');
    }

    public function createShipment(array $data): Shipment
    {
        return DB::transaction(function () use ($data) {
            $order = MoqOrder::findOrFail($data['moq_order_id']);

            if (!$order->isShippable()) {
                throw InvalidStatusTransitionException::for($order->status, MoqOrder::STATUS_SHIPPED);
            }

            $data['shipment_no'] = $data['shipment_no'] ?? $this->generateShipmentNo();
            $data['status'] = $data['status'] ?? Shipment::STATUS_PENDING;
            $data['carrier_name'] = $data['carrier_name']
                ?? ($this->carriers[$data['carrier_code']] ?? $data['carrier_code']);

            $shipment = Shipment::create($data);

            $this->updateShippedQuantity($order, $data['ship_items'] ?? []);

            $order->refresh();
            $allShipped = $order->items->every(function ($item) {
                return $item->shipped_quantity >= $item->quantity;
            });

            if ($allShipped) {
                $order->assertCanTransitionTo(MoqOrder::STATUS_SHIPPED);
                $order->update([
                    'status' => MoqOrder::STATUS_SHIPPED,
                    'shipped_at' => now(),
                ]);
            } else {
                $order->assertCanTransitionTo(MoqOrder::STATUS_PROCESSING);
                $order->update(['status' => MoqOrder::STATUS_PROCESSING]);
            }

            return $shipment->load('order.items');
        });
    }

    public function updateShipment(Shipment $shipment, array $data): Shipment
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            throw new MoqDirectShipException('只有待发货状态的发货单才能编辑');
        }

        $shipment->update($data);

        return $shipment->fresh();
    }

    public function deleteShipment(Shipment $shipment): void
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            throw new MoqDirectShipException('只有待发货状态的发货单才能删除');
        }

        DB::transaction(function () use ($shipment) {
            $order = $shipment->order;

            $shipment->delete();

            if ($order) {
                $order->refresh();
                $hasOtherShipments = $order->shipments()->exists();
                if (!$hasOtherShipments && $order->status === MoqOrder::STATUS_SHIPPED) {
                    $order->assertCanTransitionTo(MoqOrder::STATUS_PROCESSING);
                    $order->update(['status' => MoqOrder::STATUS_PROCESSING]);
                }
            }
        });
    }

    public function transitionShipment(Shipment $shipment, string $targetStatus, ?string $reason = null): Shipment
    {
        $shipment->assertCanTransitionTo($targetStatus);

        return DB::transaction(function () use ($shipment, $targetStatus, $reason) {
            $updateData = ['status' => $targetStatus];

            if ($targetStatus === Shipment::STATUS_SHIPPED) {
                $updateData['shipped_at'] = now();
            }

            if ($targetStatus === Shipment::STATUS_DELIVERED && empty($shipment->delivered_at)) {
                $updateData['delivered_at'] = now();
            }

            if ($reason && in_array($targetStatus, [Shipment::STATUS_FAILED, Shipment::STATUS_RETURNED], true)) {
                $label = $targetStatus === Shipment::STATUS_FAILED ? '派送失败原因' : '退回原因';
                $updateData['remark'] = $shipment->remark
                    ? $shipment->remark . "\n{$label}：{$reason}"
                    : "{$label}：{$reason}";
            }

            $shipment->update($updateData);

            $order = $shipment->order;
            if ($order) {
                $order->refresh();

                if ($targetStatus === Shipment::STATUS_SHIPPED) {
                    if ($order->is_fully_shipped) {
                        $order->assertCanTransitionTo(MoqOrder::STATUS_SHIPPED);
                        $order->update([
                            'status' => MoqOrder::STATUS_SHIPPED,
                            'shipped_at' => now(),
                        ]);
                    } elseif (in_array($order->status, [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PENDING], true)) {
                        $order->assertCanTransitionTo(MoqOrder::STATUS_PROCESSING);
                        $order->update(['status' => MoqOrder::STATUS_PROCESSING]);
                    }
                }

                if ($targetStatus === Shipment::STATUS_DELIVERED) {
                    $allDelivered = $order->shipments->every(function ($s) {
                        return $s->status === Shipment::STATUS_DELIVERED;
                    });

                    if ($allDelivered && $order->status === MoqOrder::STATUS_SHIPPED) {
                        $order->assertCanTransitionTo(MoqOrder::STATUS_COMPLETED);
                        $order->update([
                            'status' => MoqOrder::STATUS_COMPLETED,
                            'completed_at' => now(),
                        ]);
                    }
                }
            }

            return $shipment->fresh()->load('order');
        });
    }

    public function getShipmentStats(): array
    {
        return [
            'total' => Shipment::count(),
            'pending' => Shipment::where('status', Shipment::STATUS_PENDING)->count(),
            'shipped' => Shipment::whereIn('status', [
                Shipment::STATUS_PICKED,
                Shipment::STATUS_SHIPPED,
                Shipment::STATUS_IN_TRANSIT,
            ])->count(),
            'delivered' => Shipment::where('status', Shipment::STATUS_DELIVERED)->count(),
            'exception' => Shipment::whereIn('status', [
                Shipment::STATUS_FAILED,
                Shipment::STATUS_RETURNED,
            ])->count(),
        ];
    }
}
