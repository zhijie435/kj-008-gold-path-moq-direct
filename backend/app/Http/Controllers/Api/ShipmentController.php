<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\MoqOrder;
use App\Models\MoqOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Shipment::with(['order']);

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('shipment_no', 'like', "%{$keyword}%")
                    ->orWhere('tracking_no', 'like', "%{$keyword}%")
                    ->orWhereHas('order', function ($subQ) use ($keyword) {
                        $subQ->where('order_no', 'like', "%{$keyword}%")
                            ->orWhere('customer_name', 'like', "%{$keyword}%")
                            ->orWhere('customer_phone', 'like', "%{$keyword}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('carrier_code')) {
            $query->where('carrier_code', $request->input('carrier_code'));
        }

        if ($request->filled('moq_order_id')) {
            $query->where('moq_order_id', $request->input('moq_order_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 15);
        $shipments = $query->paginate($perPage);

        $stats = [
            'total' => Shipment::count(),
            'pending' => Shipment::where('status', Shipment::STATUS_PENDING)->count(),
            'shipped' => Shipment::whereIn('status', [Shipment::STATUS_PICKED, Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT])->count(),
            'delivered' => Shipment::where('status', Shipment::STATUS_DELIVERED)->count(),
            'exception' => Shipment::whereIn('status', [Shipment::STATUS_FAILED, Shipment::STATUS_RETURNED])->count(),
        ];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'list' => $shipments->items(),
                'total' => $shipments->total(),
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'stats' => $stats,
            ],
        ]);
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['order.items', 'order.supplier']);
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $shipment,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'moq_order_id' => 'required|exists:moq_orders,id',
            'carrier_code' => 'required|string|max:50',
            'carrier_name' => 'required|string|max:100',
            'tracking_no' => 'required|string|max:100',
            'shipping_method' => 'nullable|string|max:50',
            'shipping_cost' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'package_count' => 'nullable|integer|min:1',
            'package_info' => 'nullable|array',
            'remark' => 'nullable|string',
            'ship_items' => 'nullable|array',
            'ship_items.*.order_item_id' => 'required_with:ship_items|exists:moq_order_items,id',
            'ship_items.*.quantity' => 'required_with:ship_items|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $order = MoqOrder::findOrFail($validated['moq_order_id']);

            if (!in_array($order->status, [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PROCESSING, MoqOrder::STATUS_SHIPPED])) {
                return response()->json([
                    'code' => 422,
                    'message' => '当前订单状态不允许发货',
                    'data' => null,
                ], 422);
            }

            $shipmentNo = 'SH' . date('YmdHis') . Str::random(4);

            $shipment = Shipment::create([
                'shipment_no' => $shipmentNo,
                'moq_order_id' => $validated['moq_order_id'],
                'carrier_code' => $validated['carrier_code'],
                'carrier_name' => $validated['carrier_name'],
                'tracking_no' => $validated['tracking_no'],
                'shipping_method' => $validated['shipping_method'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'weight' => $validated['weight'] ?? 0,
                'package_count' => $validated['package_count'] ?? 1,
                'package_info' => $validated['package_info'] ?? null,
                'status' => Shipment::STATUS_PENDING,
                'remark' => $validated['remark'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (isset($validated['ship_items']) && is_array($validated['ship_items'])) {
                foreach ($validated['ship_items'] as $shipItem) {
                    $orderItem = MoqOrderItem::findOrFail($shipItem['order_item_id']);
                    $newShippedQty = $orderItem->shipped_quantity + $shipItem['quantity'];
                    if ($newShippedQty > $orderItem->quantity) {
                        throw new \Exception("商品【{$orderItem->product_name}】发货数量超过订购数量");
                    }
                    $orderItem->update(['shipped_quantity' => $newShippedQty]);
                }
            }

            $order->refresh();
            $allShipped = $order->items->every(function ($item) {
                return $item->shipped_quantity >= $item->quantity;
            });

            if ($allShipped) {
                $order->update([
                    'status' => MoqOrder::STATUS_SHIPPED,
                    'shipped_at' => now(),
                ]);
            } elseif (in_array($order->status, [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PROCESSING])) {
                $order->update(['status' => MoqOrder::STATUS_PROCESSING]);
            }

            $shipment->load(['order.items']);

            return response()->json([
                'code' => 0,
                'message' => '发货单创建成功',
                'data' => $shipment,
            ], 201);
        });
    }

    public function update(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'carrier_code' => 'sometimes|string|max:50',
            'carrier_name' => 'sometimes|string|max:100',
            'tracking_no' => 'sometimes|string|max:100',
            'shipping_method' => 'nullable|string|max:50',
            'shipping_cost' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'package_count' => 'nullable|integer|min:1',
            'package_info' => 'nullable|array',
            'remark' => 'nullable|string',
        ]);

        if ($shipment->status !== Shipment::STATUS_PENDING) {
            return response()->json([
                'code' => 422,
                'message' => '只有待发货状态的发货单才能编辑',
                'data' => null,
            ], 422);
        }

        $shipment->update($validated);

        return response()->json([
            'code' => 0,
            'message' => '发货单更新成功',
            'data' => $shipment,
        ]);
    }

    public function destroy(Shipment $shipment)
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            return response()->json([
                'code' => 422,
                'message' => '只有待发货状态的发货单才能删除',
                'data' => null,
            ], 422);
        }

        return DB::transaction(function () use ($shipment) {
            $order = $shipment->order;

            $shipment->delete();

            $order->refresh();
            $hasShipped = $order->shipments()->where('id', '!=', $shipment->id)->exists();
            if (!$hasShipped && in_array($order->status, [MoqOrder::STATUS_SHIPPED])) {
                $order->update(['status' => MoqOrder::STATUS_PROCESSING]);
            }

            return response()->json([
                'code' => 0,
                'message' => '发货单删除成功',
                'data' => null,
            ]);
        });
    }

    public function ship(Shipment $shipment)
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            return response()->json([
                'code' => 422,
                'message' => '只有待发货状态的发货单才能发货',
                'data' => null,
            ], 422);
        }

        $shipment->update([
            'status' => Shipment::STATUS_SHIPPED,
            'shipped_at' => now(),
        ]);

        $order = $shipment->order;
        if ($order) {
            $order->refresh();
            if ($order->is_fully_shipped) {
                $order->update([
                    'status' => MoqOrder::STATUS_SHIPPED,
                    'shipped_at' => now(),
                ]);
            } elseif (in_array($order->status, [MoqOrder::STATUS_CONFIRMED, MoqOrder::STATUS_PENDING])) {
                $order->update(['status' => MoqOrder::STATUS_PROCESSING]);
            }
        }

        return response()->json([
            'code' => 0,
            'message' => '发货成功',
            'data' => $shipment,
        ]);
    }

    public function markPicked(Shipment $shipment)
    {
        if (!in_array($shipment->status, [Shipment::STATUS_PENDING, Shipment::STATUS_SHIPPED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前状态不允许标记已揽收',
                'data' => null,
            ], 422);
        }

        $shipment->update(['status' => Shipment::STATUS_PICKED]);

        return response()->json([
            'code' => 0,
            'message' => '已标记揽收',
            'data' => $shipment,
        ]);
    }

    public function markInTransit(Shipment $shipment)
    {
        if (!in_array($shipment->status, [Shipment::STATUS_PICKED, Shipment::STATUS_SHIPPED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前状态不允许标记运输中',
                'data' => null,
            ], 422);
        }

        $shipment->update(['status' => Shipment::STATUS_IN_TRANSIT]);

        return response()->json([
            'code' => 0,
            'message' => '已标记运输中',
            'data' => $shipment,
        ]);
    }

    public function markDelivered(Shipment $shipment, Request $request)
    {
        if (!in_array($shipment->status, [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_SHIPPED, Shipment::STATUS_PICKED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前状态不允许标记已签收',
                'data' => null,
            ], 422);
        }

        $shipment->update([
            'status' => Shipment::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $order = $shipment->order;
        $order->load('shipments');
        $allDelivered = $order->shipments->every(function ($s) {
            return $s->status === Shipment::STATUS_DELIVERED;
        });

        if ($allDelivered && $order->status === MoqOrder::STATUS_SHIPPED) {
            $order->update([
                'status' => MoqOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'code' => 0,
            'message' => '已标记签收',
            'data' => $shipment,
        ]);
    }

    public function markFailed(Shipment $shipment, Request $request)
    {
        if (in_array($shipment->status, [Shipment::STATUS_DELIVERED, Shipment::STATUS_RETURNED])) {
            return response()->json([
                'code' => 422,
                'message' => '当前状态不允许标记派送失败',
                'data' => null,
            ], 422);
        }

        $shipment->update([
            'status' => Shipment::STATUS_FAILED,
            'remark' => $request->input('reason') ? ($shipment->remark ? $shipment->remark . "\n" : '') . '派送失败原因：' . $request->input('reason') : $shipment->remark,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '已标记派送失败',
            'data' => $shipment,
        ]);
    }

    public function markReturned(Shipment $shipment, Request $request)
    {
        if ($shipment->status === Shipment::STATUS_DELIVERED) {
            return response()->json([
                'code' => 422,
                'message' => '已签收的发货单不能标记退回',
                'data' => null,
            ], 422);
        }

        $shipment->update([
            'status' => Shipment::STATUS_RETURNED,
            'remark' => $request->input('reason') ? ($shipment->remark ? $shipment->remark . "\n" : '') . '退回原因：' . $request->input('reason') : $shipment->remark,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '已标记退回',
            'data' => $shipment,
        ]);
    }

    public function getStatusOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => Shipment::getStatusOptions(),
        ]);
    }

    public function getCarrierOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => Shipment::getCarrierOptions(),
        ]);
    }

    public function updateTracking(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'tracking_data' => 'nullable|array',
            'status' => 'nullable|string',
        ]);

        $trackingData = $validated['tracking_data'] ?? [];
        if (!empty($validated['status'])) {
            $trackingData['status'] = $validated['status'];
        }

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
                    $shipment->update(['status' => $status]);

                    if ($status === Shipment::STATUS_DELIVERED && empty($shipment->delivered_at)) {
                        $shipment->update(['delivered_at' => now()]);

                        $order = $shipment->order;
                        if ($order) {
                            $order->load('shipments');
                            $allDelivered = $order->shipments->every(function ($s) {
                                return $s->status === Shipment::STATUS_DELIVERED;
                            });

                            if ($allDelivered && $order->status === MoqOrder::STATUS_SHIPPED) {
                                $order->update([
                                    'status' => MoqOrder::STATUS_COMPLETED,
                                    'completed_at' => now(),
                                ]);
                            }
                        }
                    }
                    break;
                }
            }
        }

        $shipment->load('order');

        return response()->json([
            'code' => 0,
            'message' => '物流信息更新成功',
            'data' => $shipment,
        ]);
    }
}
