<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MoqOrder;
use App\Models\MoqOrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoqOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = MoqOrder::with(['supplier', 'items', 'shipments']);

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', "%{$keyword}%")
                    ->orWhere('customer_name', 'like', "%{$keyword}%")
                    ->orWhere('customer_phone', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);

        $stats = [
            'total' => MoqOrder::count(),
            'pending' => MoqOrder::where('status', MoqOrder::STATUS_PENDING)->count(),
            'processing' => MoqOrder::whereIn('status', [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PROCESSING])->count(),
            'shipped' => MoqOrder::where('status', MoqOrder::STATUS_SHIPPED)->count(),
            'completed' => MoqOrder::where('status', MoqOrder::STATUS_COMPLETED)->count(),
            'today_amount' => MoqOrder::whereDate('created_at', today())->sum('payable_amount'),
            'month_amount' => MoqOrder::whereMonth('created_at', now()->month)->sum('payable_amount'),
        ];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'list' => $orders->items(),
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'stats' => $stats,
            ],
        ]);
    }

    public function show(MoqOrder $order)
    {
        $order->load(['supplier', 'items', 'shipments']);
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'customer_name' => 'required|string|max:100',
            'customer_phone' => 'required|string|max:20',
            'province' => 'required|string|max:50',
            'city' => 'required|string|max:50',
            'district' => 'required|string|max:50',
            'address' => 'required|string|max:500',
            'address_detail' => 'nullable|string|max:200',
            'shipping_fee' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:20',
            'source' => 'nullable|string|max:20',
            'remark' => 'nullable|string',
            'internal_note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string|max:200',
            'items.*.product_sku' => 'required|string|max:50',
            'items.*.specification' => 'nullable|string|max:200',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.remark' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $orderNo = 'MOQ' . date('YmdHis') . Str::random(4);

            $items = $validated['items'];
            $totalAmount = collect($items)->sum(function ($item) {
                return ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
            });

            $shippingFee = $validated['shipping_fee'] ?? 0;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $payableAmount = $totalAmount + $shippingFee - $discountAmount;

            $order = MoqOrder::create([
                'order_no' => $orderNo,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'province' => $validated['province'],
                'city' => $validated['city'],
                'district' => $validated['district'],
                'address' => $validated['address'],
                'address_detail' => $validated['address_detail'] ?? null,
                'total_amount' => $totalAmount,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $discountAmount,
                'payable_amount' => $payableAmount,
                'paid_amount' => 0,
                'payment_method' => $validated['payment_method'] ?? null,
                'status' => MoqOrder::STATUS_PENDING,
                'source' => $validated['source'] ?? MoqOrder::SOURCE_MANUAL,
                'remark' => $validated['remark'] ?? null,
                'internal_note' => $validated['internal_note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                MoqOrderItem::create([
                    'moq_order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['product_sku'],
                    'specification' => $item['specification'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0),
                    'cost_price' => $item['cost_price'] ?? 0,
                    'shipped_quantity' => 0,
                    'remark' => $item['remark'] ?? null,
                ]);
            }

            $order->load(['items', 'supplier']);

            return response()->json([
                'code' => 0,
                'message' => '订单创建成功',
                'data' => $order,
            ], 201);
        });
    }

    public function update(Request $request, MoqOrder $order)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'customer_name' => 'sometimes|string|max:100',
            'customer_phone' => 'sometimes|string|max:20',
            'province' => 'sometimes|string|max:50',
            'city' => 'sometimes|string|max:50',
            'district' => 'sometimes|string|max:50',
            'address' => 'sometimes|string|max:500',
            'address_detail' => 'nullable|string|max:200',
            'shipping_fee' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:20',
            'paid_amount' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
            'internal_note' => 'nullable|string',
        ]);

        $order->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        if (isset($validated['shipping_fee']) || isset($validated['discount_amount'])) {
            $totalAmount = $order->items->sum('total_price');
            $shippingFee = $validated['shipping_fee'] ?? $order->shipping_fee;
            $discountAmount = $validated['discount_amount'] ?? $order->discount_amount;
            $order->update([
                'total_amount' => $totalAmount,
                'payable_amount' => $totalAmount + $shippingFee - $discountAmount,
            ]);
        }

        $order->load(['items', 'supplier', 'shipments']);

        return response()->json([
            'code' => 0,
            'message' => '订单更新成功',
            'data' => $order,
        ]);
    }

    public function destroy(MoqOrder $order)
    {
        if ($order->status !== MoqOrder::STATUS_PENDING && $order->status !== MoqOrder::STATUS_CANCELLED) {
            return response()->json([
                'code' => 422,
                'message' => '只有待确认或已取消的订单才能删除',
                'data' => null,
            ], 422);
        }

        $order->delete();

        return response()->json([
            'code' => 0,
            'message' => '订单删除成功',
            'data' => null,
        ]);
    }

    public function confirm(MoqOrder $order)
    {
        if ($order->status !== MoqOrder::STATUS_PENDING) {
            return response()->json([
                'code' => 422,
                'message' => '只有待确认的订单才能确认',
                'data' => null,
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '订单确认成功',
            'data' => $order,
        ]);
    }

    public function cancel(MoqOrder $order, Request $request)
    {
        if (in_array($order->status, [MoqOrder::STATUS_COMPLETED, MoqOrder::STATUS_CANCELLED, MoqOrder::STATUS_REFUNDED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前订单状态不允许取消',
                'data' => null,
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_CANCELLED,
            'internal_note' => $request->input('reason') ? ($order->internal_note ? $order->internal_note . "\n" : '') . '取消原因：' . $request->input('reason') : $order->internal_note,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '订单取消成功',
            'data' => $order,
        ]);
    }

    public function startProcessing(MoqOrder $order)
    {
        if (!in_array($order->status, [MoqOrder::STATUS_CONFIRMED])) {
            return response()->json([
                'code' => 422,
                'message' => '只有已确认的订单才能开始处理',
                'data' => null,
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_PROCESSING,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '订单已开始处理',
            'data' => $order,
        ]);
    }

    public function complete(MoqOrder $order)
    {
        if (!in_array($order->status, [MoqOrder::STATUS_SHIPPED])) {
            return response()->json([
                'code' => 422,
                'message' => '只有已发货的订单才能完成',
                'data' => null,
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '订单已完成',
            'data' => $order,
        ]);
    }

    public function getStatusOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => MoqOrder::getStatusOptions(),
        ]);
    }

    public function getSourceOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => MoqOrder::getSourceOptions(),
        ]);
    }

    public function getPaymentOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => MoqOrder::getPaymentOptions(),
        ]);
    }

    public function updatePayment(Request $request, MoqOrder $order)
    {
        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:20',
        ]);

        $newPaidAmount = $order->paid_amount + $validated['paid_amount'];

        $order->update([
            'paid_amount' => min($newPaidAmount, $order->payable_amount),
            'payment_method' => $validated['payment_method'],
            'paid_at' => $order->paid_at ?? now(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '支付信息更新成功',
            'data' => $order,
        ]);
    }

    public function statistics(Request $request)
    {
        $dateStart = $request->input('date_start', now()->startOfMonth()->toDateString());
        $dateEnd = $request->input('date_end', now()->endOfMonth()->toDateString());

        $orderQuery = MoqOrder::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59']);

        $totalOrders = $orderQuery->count();
        $totalAmount = $orderQuery->sum('payable_amount');
        $paidAmount = $orderQuery->sum('paid_amount');

        $statusStats = MoqOrder::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->selectRaw('status, COUNT(*) as count, SUM(payable_amount) as amount')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $productCount = \App\Models\Product::count();
        $activeProductCount = \App\Models\Product::where('is_active', true)->count();
        $lowStockCount = \App\Models\Product::whereRaw('stock_quantity <= safety_stock')->count();

        $supplierCount = \App\Models\Supplier::count();
        $activeSupplierCount = \App\Models\Supplier::where('is_active', true)->count();

        $shipmentCount = \App\Models\Shipment::whereBetween('shipped_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->count();
        $deliveredCount = \App\Models\Shipment::whereBetween('delivered_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->count();

        $pendingCount = $statusStats[MoqOrder::STATUS_PENDING] ?? 0;
        $confirmedCount = $statusStats[MoqOrder::STATUS_CONFIRMED] ?? 0;
        $processingCount = $statusStats[MoqOrder::STATUS_PROCESSING] ?? 0;
        $shippedCount = $statusStats[MoqOrder::STATUS_SHIPPED] ?? 0;
        $completedCount = $statusStats[MoqOrder::STATUS_COMPLETED] ?? 0;
        $cancelledCount = $statusStats[MoqOrder::STATUS_CANCELLED] ?? 0;

        $stats = [
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

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $stats,
        ]);
    }

    public function ship(Request $request, MoqOrder $order)
    {
        $validated = $request->validate([
            'carrier_code' => 'required|string|max:50',
            'carrier_name' => 'nullable|string|max:100',
            'tracking_no' => 'required|string|max:100',
            'shipping_method' => 'nullable|string|max:50',
            'shipping_cost' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'package_count' => 'nullable|integer|min:1',
            'package_info' => 'nullable|array',
            'remark' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.order_item_id' => 'required_with:items|exists:moq_order_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        $allowedStatuses = [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PROCESSING, MoqOrder::STATUS_SHIPPED];
        if (!in_array($order->status, $allowedStatuses)) {
            return response()->json([
                'code' => 422,
                'message' => '当前订单状态不支持发货',
                'data' => null,
            ], 422);
        }

        return DB::transaction(function () use ($order, $validated) {
            $carriers = collect(\App\Models\Shipment::getCarrierOptions())
                ->pluck('label', 'value')
                ->toArray();

            $shipmentNo = 'SH' . date('YmdHis') . Str::random(4);
            $carrierName = $validated['carrier_name']
                ?? ($carriers[$validated['carrier_code']] ?? $validated['carrier_code']);

            $shipment = \App\Models\Shipment::create([
                'shipment_no' => $shipmentNo,
                'moq_order_id' => $order->id,
                'carrier_code' => $validated['carrier_code'],
                'carrier_name' => $carrierName,
                'tracking_no' => $validated['tracking_no'],
                'shipping_method' => $validated['shipping_method'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'weight' => $validated['weight'] ?? 0,
                'package_count' => $validated['package_count'] ?? 1,
                'package_info' => $validated['package_info'] ?? null,
                'status' => \App\Models\Shipment::STATUS_SHIPPED,
                'shipped_at' => now(),
                'remark' => $validated['remark'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (isset($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $shipItem) {
                    $orderItem = MoqOrderItem::find($shipItem['order_item_id']);
                    if ($orderItem && $orderItem->moq_order_id == $order->id) {
                        $newShipped = $orderItem->shipped_quantity + ($shipItem['quantity'] ?? 0);
                        if ($newShipped > $orderItem->quantity) {
                            throw new \InvalidArgumentException("发货数量不能超过订购数量");
                        }
                        $orderItem->update(['shipped_quantity' => $newShipped]);
                    }
                }
            }

            $order->refresh();
            if ($order->is_fully_shipped) {
                $order->update([
                    'status' => MoqOrder::STATUS_SHIPPED,
                    'shipped_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            } else {
                $order->update([
                    'status' => MoqOrder::STATUS_PROCESSING,
                    'updated_by' => auth()->id(),
                ]);
            }

            $shipment->load('order');

            return response()->json([
                'code' => 0,
                'message' => '发货成功',
                'data' => $shipment,
            ]);
        });
    }

    public function pay(Request $request, MoqOrder $order)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:20',
        ]);

        if (in_array($order->status, [MoqOrder::STATUS_CANCELLED, MoqOrder::STATUS_REFUNDED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前订单状态不支持支付',
                'data' => null,
            ], 422);
        }

        $newPaidAmount = $order->paid_amount + $validated['amount'];
        if ($newPaidAmount > $order->payable_amount) {
            return response()->json([
                'code' => 422,
                'message' => '支付金额超过应付金额',
                'data' => null,
            ], 422);
        }

        $order->update([
            'paid_amount' => $newPaidAmount,
            'payment_method' => $validated['payment_method'],
            'paid_at' => $newPaidAmount >= $order->payable_amount ? now() : $order->paid_at,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '支付成功',
            'data' => $order,
        ]);
    }

    public function refund(Request $request, MoqOrder $order)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        if (!in_array($order->status, [MoqOrder::STATUS_SHIPPED, MoqOrder::STATUS_COMPLETED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前订单状态不支持退款',
                'data' => null,
            ], 422);
        }

        $amount = $validated['amount'];
        if ($amount <= 0 || $amount > $order->paid_amount) {
            return response()->json([
                'code' => 422,
                'message' => '退款金额无效',
                'data' => null,
            ], 422);
        }

        $order->update([
            'paid_amount' => $order->paid_amount - $amount,
            'internal_note' => $order->internal_note
                ? $order->internal_note . "\n退款: {$amount}, 原因: " . ($validated['reason'] ?? '')
                : "退款: {$amount}, 原因: " . ($validated['reason'] ?? ''),
            'updated_by' => auth()->id(),
        ]);

        if ($order->paid_amount <= 0 && in_array($order->status, [MoqOrder::STATUS_COMPLETED, MoqOrder::STATUS_SHIPPED])) {
            $order->update(['status' => MoqOrder::STATUS_REFUNDED]);
        }

        return response()->json([
            'code' => 0,
            'message' => '退款成功',
            'data' => $order,
        ]);
    }
}
