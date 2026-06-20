<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => '在线课程教务系统 - 国内MOQ直发 API',
        'version' => '1.0.0',
    ]);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    Route::prefix('suppliers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SupplierController::class, 'index']);
        Route::get('/all-active', [\App\Http\Controllers\Api\SupplierController::class, 'allActive']);
        Route::get('/status-options', [\App\Http\Controllers\Api\SupplierController::class, 'getStatusOptions']);
        Route::post('/', [\App\Http\Controllers\Api\SupplierController::class, 'store']);
        Route::get('/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'show']);
        Route::put('/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'update']);
        Route::delete('/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'destroy']);
        Route::post('/{supplier}/toggle-active', [\App\Http\Controllers\Api\SupplierController::class, 'toggleActive']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ProductController::class, 'index']);
        Route::get('/unit-options', [\App\Http\Controllers\Api\ProductController::class, 'getUnitOptions']);
        Route::get('/status-options', [\App\Http\Controllers\Api\ProductController::class, 'getStatusOptions']);
        Route::post('/', [\App\Http\Controllers\Api\ProductController::class, 'store']);
        Route::get('/{product}', [\App\Http\Controllers\Api\ProductController::class, 'show']);
        Route::put('/{product}', [\App\Http\Controllers\Api\ProductController::class, 'update']);
        Route::delete('/{product}', [\App\Http\Controllers\Api\ProductController::class, 'destroy']);
        Route::post('/{product}/toggle-active', [\App\Http\Controllers\Api\ProductController::class, 'toggleActive']);
        Route::post('/{product}/update-stock', [\App\Http\Controllers\Api\ProductController::class, 'updateStock']);
    });

    Route::prefix('moq-orders')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\MoqOrderController::class, 'index']);
        Route::get('/status-options', [\App\Http\Controllers\Api\MoqOrderController::class, 'getStatusOptions']);
        Route::get('/source-options', [\App\Http\Controllers\Api\MoqOrderController::class, 'getSourceOptions']);
        Route::get('/payment-options', [\App\Http\Controllers\Api\MoqOrderController::class, 'getPaymentOptions']);
        Route::post('/', [\App\Http\Controllers\Api\MoqOrderController::class, 'store']);
        Route::get('/{order}', [\App\Http\Controllers\Api\MoqOrderController::class, 'show']);
        Route::put('/{order}', [\App\Http\Controllers\Api\MoqOrderController::class, 'update']);
        Route::delete('/{order}', [\App\Http\Controllers\Api\MoqOrderController::class, 'destroy']);
        Route::post('/{order}/confirm', [\App\Http\Controllers\Api\MoqOrderController::class, 'confirm']);
        Route::post('/{order}/cancel', [\App\Http\Controllers\Api\MoqOrderController::class, 'cancel']);
        Route::post('/{order}/start-processing', [\App\Http\Controllers\Api\MoqOrderController::class, 'startProcessing']);
        Route::post('/{order}/complete', [\App\Http\Controllers\Api\MoqOrderController::class, 'complete']);
        Route::post('/{order}/update-payment', [\App\Http\Controllers\Api\MoqOrderController::class, 'updatePayment']);
    });

    Route::prefix('shipments')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ShipmentController::class, 'index']);
        Route::get('/status-options', [\App\Http\Controllers\Api\ShipmentController::class, 'getStatusOptions']);
        Route::get('/carrier-options', [\App\Http\Controllers\Api\ShipmentController::class, 'getCarrierOptions']);
        Route::post('/', [\App\Http\Controllers\Api\ShipmentController::class, 'store']);
        Route::get('/{shipment}', [\App\Http\Controllers\Api\ShipmentController::class, 'show']);
        Route::put('/{shipment}', [\App\Http\Controllers\Api\ShipmentController::class, 'update']);
        Route::delete('/{shipment}', [\App\Http\Controllers\Api\ShipmentController::class, 'destroy']);
        Route::post('/{shipment}/ship', [\App\Http\Controllers\Api\ShipmentController::class, 'ship']);
        Route::post('/{shipment}/mark-picked', [\App\Http\Controllers\Api\ShipmentController::class, 'markPicked']);
        Route::post('/{shipment}/mark-in-transit', [\App\Http\Controllers\Api\ShipmentController::class, 'markInTransit']);
        Route::post('/{shipment}/mark-delivered', [\App\Http\Controllers\Api\ShipmentController::class, 'markDelivered']);
        Route::post('/{shipment}/mark-failed', [\App\Http\Controllers\Api\ShipmentController::class, 'markFailed']);
        Route::post('/{shipment}/mark-returned', [\App\Http\Controllers\Api\ShipmentController::class, 'markReturned']);
    });

    Route::prefix('moq-direct-ship')->group(function () {
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\SupplierController::class, 'index']);
            Route::get('/all', [\App\Http\Controllers\Api\SupplierController::class, 'allActive']);
            Route::get('/status-options', [\App\Http\Controllers\Api\SupplierController::class, 'getStatusOptions']);
            Route::post('/', [\App\Http\Controllers\Api\SupplierController::class, 'store']);
            Route::get('/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'show']);
            Route::put('/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'update']);
            Route::delete('/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'destroy']);
            Route::post('/{supplier}/toggle-status', [\App\Http\Controllers\Api\SupplierController::class, 'toggleActive']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ProductController::class, 'index']);
            Route::get('/categories', [\App\Http\Controllers\Api\ProductController::class, 'getCategories']);
            Route::get('/unit-options', [\App\Http\Controllers\Api\ProductController::class, 'getUnitOptions']);
            Route::get('/status-options', [\App\Http\Controllers\Api\ProductController::class, 'getStatusOptions']);
            Route::post('/', [\App\Http\Controllers\Api\ProductController::class, 'store']);
            Route::get('/{product}', [\App\Http\Controllers\Api\ProductController::class, 'show']);
            Route::put('/{product}', [\App\Http\Controllers\Api\ProductController::class, 'update']);
            Route::delete('/{product}', [\App\Http\Controllers\Api\ProductController::class, 'destroy']);
            Route::post('/{product}/toggle-status', [\App\Http\Controllers\Api\ProductController::class, 'toggleActive']);
            Route::post('/{product}/update-stock', [\App\Http\Controllers\Api\ProductController::class, 'updateStock']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MoqOrderController::class, 'index']);
            Route::get('/statistics', [\App\Http\Controllers\Api\MoqOrderController::class, 'statistics']);
            Route::get('/status-options', [\App\Http\Controllers\Api\MoqOrderController::class, 'getStatusOptions']);
            Route::get('/source-options', [\App\Http\Controllers\Api\MoqOrderController::class, 'getSourceOptions']);
            Route::get('/payment-options', [\App\Http\Controllers\Api\MoqOrderController::class, 'getPaymentOptions']);
            Route::post('/', [\App\Http\Controllers\Api\MoqOrderController::class, 'store']);
            Route::get('/{order}', [\App\Http\Controllers\Api\MoqOrderController::class, 'show']);
            Route::put('/{order}', [\App\Http\Controllers\Api\MoqOrderController::class, 'update']);
            Route::delete('/{order}', [\App\Http\Controllers\Api\MoqOrderController::class, 'destroy']);
            Route::post('/{order}/confirm', [\App\Http\Controllers\Api\MoqOrderController::class, 'confirm']);
            Route::post('/{order}/cancel', [\App\Http\Controllers\Api\MoqOrderController::class, 'cancel']);
            Route::post('/{order}/process', [\App\Http\Controllers\Api\MoqOrderController::class, 'startProcessing']);
            Route::post('/{order}/ship', [\App\Http\Controllers\Api\MoqOrderController::class, 'ship']);
            Route::post('/{order}/complete', [\App\Http\Controllers\Api\MoqOrderController::class, 'complete']);
            Route::post('/{order}/pay', [\App\Http\Controllers\Api\MoqOrderController::class, 'pay']);
            Route::post('/{order}/refund', [\App\Http\Controllers\Api\MoqOrderController::class, 'refund']);
        });

        Route::prefix('shipments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ShipmentController::class, 'index']);
            Route::get('/status-options', [\App\Http\Controllers\Api\ShipmentController::class, 'getStatusOptions']);
            Route::get('/carrier-options', [\App\Http\Controllers\Api\ShipmentController::class, 'getCarrierOptions']);
            Route::post('/', [\App\Http\Controllers\Api\ShipmentController::class, 'store']);
            Route::get('/{shipment}', [\App\Http\Controllers\Api\ShipmentController::class, 'show']);
            Route::put('/{shipment}', [\App\Http\Controllers\Api\ShipmentController::class, 'update']);
            Route::post('/{shipment}/update-tracking', [\App\Http\Controllers\Api\ShipmentController::class, 'updateTracking']);
        });
    });
});
