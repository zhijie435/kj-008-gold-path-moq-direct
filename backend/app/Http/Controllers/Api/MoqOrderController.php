<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MoqOrder;
use App\Services\MoqDirectShipService;
use Illuminate\Http\Request;

class MoqOrderController extends Controller
{
    public function __construct(protected MoqDirectShipService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('view-moq-orders');

        $orders = $this->service->getOrderList([
            'keyword' => $request->input('keyword'),
            'status' => $request->input('status'),
            'supplier_id' => $request->input('supplier_id'),
            'start_date' => $request->input('date_from'),
            'end_date' => $request->input('date_to'),
            'source' => $request->input('source'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page', 15),
        ]);

        return $this->respondPaginated($orders);
    }

    public function show(MoqOrder $order)
    {
        $this->authorize('view-moq-orders');

        return $this->respond($order->load(['supplier', 'items', 'shipments']));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-moq-orders');

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
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.remark' => 'nullable|string',
        ]);

        $order = $this->service->createOrder(array_merge($validated, [
            'created_by' => $request->user()?->id,
        ]));

        return $this->respondCreated($order, '订单创建成功');
    }

    public function update(Request $request, MoqOrder $order)
    {
        $this->authorize('manage-moq-orders');

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

        $order = $this->service->updateOrder($order, array_merge($validated, [
            'updated_by' => $request->user()?->id,
        ]));

        return $this->respond($order, '订单更新成功');
    }

    public function destroy(MoqOrder $order)
    {
        $this->authorize('delete-moq-orders');

        $this->service->deleteOrder($order);

        return $this->respond(null, '订单删除成功');
    }

    public function confirm(MoqOrder $order)
    {
        $this->authorize('manage-moq-orders');

        return $this->respond($this->service->confirmOrder($order), '订单确认成功');
    }

    public function cancel(MoqOrder $order, Request $request)
    {
        $this->authorize('manage-moq-orders');

        return $this->respond(
            $this->service->cancelOrder($order, $request->input('reason', '')),
            '订单取消成功'
        );
    }

    public function startProcessing(MoqOrder $order)
    {
        $this->authorize('manage-moq-orders');

        return $this->respond($this->service->processOrder($order), '订单已开始处理');
    }

    public function ship(Request $request, MoqOrder $order)
    {
        $this->authorize('manage-moq-orders');

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

        $shipment = $this->service->shipOrder($order, array_merge($validated, [
            'created_by' => $request->user()?->id,
        ]));

        return $this->respondCreated($shipment, '发货成功');
    }

    public function complete(MoqOrder $order)
    {
        $this->authorize('manage-moq-orders');

        return $this->respond($this->service->completeOrder($order), '订单已完成');
    }

    public function pay(Request $request, MoqOrder $order)
    {
        $this->authorize('finance-moq-orders');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:20',
        ]);

        return $this->respond(
            $this->service->payOrder($order, (float) $validated['amount'], $validated['payment_method']),
            '支付成功'
        );
    }

    public function refund(Request $request, MoqOrder $order)
    {
        $this->authorize('finance-moq-orders');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        return $this->respond(
            $this->service->refundOrder($order, (float) $validated['amount'], $validated['reason'] ?? ''),
            '退款成功'
        );
    }

    public function updatePayment(Request $request, MoqOrder $order)
    {
        $this->authorize('finance-moq-orders');

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:20',
        ]);

        $newPaidAmount = $order->paid_amount + $validated['paid_amount'];

        $order->update([
            'paid_amount' => min($newPaidAmount, $order->payable_amount),
            'payment_method' => $validated['payment_method'],
            'paid_at' => $order->paid_at ?? now(),
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respond(
            $order->fresh()->load(['items', 'supplier', 'shipments']),
            '支付信息更新成功'
        );
    }

    public function statistics(Request $request)
    {
        $this->authorize('view-moq-orders');

        return $this->respond($this->service->getStatistics([
            'date_start' => $request->input('date_start'),
            'date_end' => $request->input('date_end'),
        ]));
    }

    public function getStatusOptions()
    {
        $this->authorize('view-moq-orders');

        return $this->respond(MoqOrder::getStatusOptions());
    }

    public function getSourceOptions()
    {
        $this->authorize('view-moq-orders');

        return $this->respond(MoqOrder::getSourceOptions());
    }

    public function getPaymentOptions()
    {
        $this->authorize('view-moq-orders');

        return $this->respond(MoqOrder::getPaymentOptions());
    }
}
