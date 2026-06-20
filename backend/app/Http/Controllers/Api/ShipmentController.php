<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\MoqDirectShipService;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(protected MoqDirectShipService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('view-shipments');

        $shipments = $this->service->getShipmentList([
            'keyword' => $request->input('keyword'),
            'status' => $request->input('status'),
            'carrier_code' => $request->input('carrier_code'),
            'moq_order_id' => $request->input('moq_order_id'),
            'start_date' => $request->input('date_from'),
            'end_date' => $request->input('date_to'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page', 15),
        ]);

        return $this->respondPaginated(
            $shipments,
            'success',
            ['stats' => $this->service->getShipmentStats()]
        );
    }

    public function show(Shipment $shipment)
    {
        $this->authorize('view-shipments');

        return $this->respond($shipment->load(['order.items', 'order.supplier']));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-shipments');

        $validated = $request->validate([
            'moq_order_id' => 'required|exists:moq_orders,id',
            'carrier_code' => 'required|string|max:50',
            'carrier_name' => 'nullable|string|max:100',
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

        $shipment = $this->service->createShipment(array_merge($validated, [
            'created_by' => $request->user()?->id,
        ]));

        return $this->respondCreated($shipment, '发货单创建成功');
    }

    public function update(Request $request, Shipment $shipment)
    {
        $this->authorize('manage-shipments');

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

        $shipment = $this->service->updateShipment($shipment, $validated);

        return $this->respond($shipment, '发货单更新成功');
    }

    public function destroy(Shipment $shipment)
    {
        $this->authorize('delete-shipments');

        $this->service->deleteShipment($shipment);

        return $this->respond(null, '发货单删除成功');
    }

    public function ship(Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        return $this->respond(
            $this->service->transitionShipment($shipment, Shipment::STATUS_SHIPPED),
            '发货成功'
        );
    }

    public function markPicked(Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        return $this->respond(
            $this->service->transitionShipment($shipment, Shipment::STATUS_PICKED),
            '已标记揽收'
        );
    }

    public function markInTransit(Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        return $this->respond(
            $this->service->transitionShipment($shipment, Shipment::STATUS_IN_TRANSIT),
            '已标记运输中'
        );
    }

    public function markDelivered(Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        return $this->respond(
            $this->service->transitionShipment($shipment, Shipment::STATUS_DELIVERED),
            '已标记签收'
        );
    }

    public function markFailed(Request $request, Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        return $this->respond(
            $this->service->transitionShipment(
                $shipment,
                Shipment::STATUS_FAILED,
                $request->input('reason')
            ),
            '已标记派送失败'
        );
    }

    public function markReturned(Request $request, Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        return $this->respond(
            $this->service->transitionShipment(
                $shipment,
                Shipment::STATUS_RETURNED,
                $request->input('reason')
            ),
            '已标记退回'
        );
    }

    public function getStatusOptions()
    {
        $this->authorize('view-shipments');

        return $this->respond(Shipment::getStatusOptions());
    }

    public function getCarrierOptions()
    {
        $this->authorize('view-shipments');

        return $this->respond(Shipment::getCarrierOptions());
    }

    public function updateTracking(Request $request, Shipment $shipment)
    {
        $this->authorize('manage-shipments');

        $validated = $request->validate([
            'tracking_data' => 'nullable|array',
            'status' => 'nullable|string',
        ]);

        $trackingData = $validated['tracking_data'] ?? [];
        if (!empty($validated['status'])) {
            $trackingData['status'] = $validated['status'];
        }

        $shipment = $this->service->updateTracking($shipment, $trackingData);

        return $this->respond($shipment, '物流信息更新成功');
    }
}
