<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_no', 50)->unique();
            $table->unsignedBigInteger('moq_order_id');
            $table->string('carrier_code', 50);
            $table->string('carrier_name', 100);
            $table->string('tracking_no', 100);
            $table->string('shipping_method', 50)->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('weight', 10, 2)->default(0);
            $table->integer('package_count')->default(1);
            $table->text('package_info')->nullable();
            $table->enum('status', ['pending', 'picked', 'shipped', 'in_transit', 'delivered', 'failed', 'returned'])
                ->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('tracking_data')->nullable();
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('moq_order_id')->references('id')->on('moq_orders')->onDelete('restrict');
            $table->index(['moq_order_id', 'status']);
            $table->index(['carrier_code', 'tracking_no']);
            $table->index(['status', 'shipped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
