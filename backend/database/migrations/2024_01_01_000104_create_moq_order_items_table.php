<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moq_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('moq_order_id')->comment('订单ID');
            $table->unsignedBigInteger('product_id')->nullable()->comment('商品ID');
            $table->string('product_name', 200)->comment('商品名称');
            $table->string('product_sku', 50)->comment('商品SKU');
            $table->string('specification', 200)->nullable()->comment('规格');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->decimal('unit_price', 10, 2)->default(0)->comment('单价');
            $table->decimal('total_price', 10, 2)->default(0)->comment('小计');
            $table->decimal('cost_price', 10, 2)->default(0)->comment('成本价');
            $table->integer('shipped_quantity')->default(0)->comment('已发货数量');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->foreign('moq_order_id')->references('id')->on('moq_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->index(['moq_order_id', 'product_id']);
            $table->index('product_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moq_order_items');
    }
};
