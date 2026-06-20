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
            'data' => $orders->items(),
            'total' => $orders->total(),
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'stats' => $stats,
        ]);
    }

    public function show(MoqOrder $order)
    {
        $order->load(['supplier', 'items', 'shipments']);
        return response()->json(['data' => $order]);
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
            'message' => '订单更新成功',
            'data' => $order,
        ]);
    }

    public function destroy(MoqOrder $order)
    {
        if ($order->status !== MoqOrder::STATUS_PENDING && $order->status !== MoqOrder::STATUS_CANCELLED) {
            return response()->json([
                'message' => '只有待确认或已取消的订单才能删除',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'message' => '订单删除成功',
        ]);
    }

    public function confirm(MoqOrder $order)
    {
        if ($order->status !== MoqOrder::STATUS_PENDING) {
            return response()->json([
                'message' => '只有待确认的订单才能确认',
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => '订单确认成功',
            'data' => $order,
        ]);
    }

    public function cancel(MoqOrder $order, Request $request)
    {
        if (in_array($order->status, [MoqOrder::STATUS_COMPLETED, MoqOrder::STATUS_CANCELLED, MoqOrder::STATUS_REFUNDED])) {
            return response()->json([
                'message' => '当前订单状态不允许取消',
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_CANCELLED,
            'internal_note' => $request->input('reason') ? ($order->internal_note ? $order->internal_note . "\n" : '') . '取消原因：' . $request->input('reason') : $order->internal_note,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => '订单取消成功',
            'data' => $order,
        ]);
    }

    public function startProcessing(MoqOrder $order)
    {
        if (!in_array($order->status, [MoqOrder::STATUS_CONFIRMED])) {
            return response()->json([
                'message' => '只有已确认的订单才能开始处理',
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_PROCESSING,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => '订单已开始处理',
            'data' => $order,
        ]);
    }

    public function complete(MoqOrder $order)
    {
        if (!in_array($order->status, [MoqOrder::STATUS_SHIPPED])) {
            return response()->json([
                'message' => '只有已发货的订单才能完成',
            ], 422);
        }

        $order->update([
            'status' => MoqOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => '订单已完成',
            'data' => $order,
        ]);
    }

    public function getStatusOptions()
    {
        return response()->json([
            'data' => MoqOrder::getStatusOptions(),
        ]);
    }

    public function getSourceOptions()
    {
        return response()->json([
            'data' => MoqOrder::getSourceOptions(),
        ]);
    }

    public function getPaymentOptions()
    {
        return response()->json([
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
            'message' => '支付信息更新成功',
            'data' => $order,
        ]);
    }
}
