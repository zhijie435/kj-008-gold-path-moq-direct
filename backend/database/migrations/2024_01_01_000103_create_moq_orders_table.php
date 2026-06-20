<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moq_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique()->comment('订单号');
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('供应商ID');
            $table->string('customer_name', 100)->comment('客户姓名');
            $table->string('customer_phone', 20)->comment('客户电话');
            $table->string('province', 50)->comment('省份');
            $table->string('city', 50)->comment('城市');
            $table->string('district', 50)->comment('区县');
            $table->string('address', 500)->comment('地址');
            $table->string('address_detail', 200)->nullable()->comment('详细地址补充');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('商品总额');
            $table->decimal('shipping_fee', 10, 2)->default(0)->comment('运费');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠金额');
            $table->decimal('payable_amount', 10, 2)->default(0)->comment('应付金额');
            $table->decimal('paid_amount', 10, 2)->default(0)->comment('已付金额');
            $table->string('payment_method', 20)->nullable()->comment('支付方式');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'])
                ->default('pending')->comment('订单状态');
            $table->enum('source', ['manual', 'api', 'import'])->default('manual')->comment('来源');
            $table->text('remark')->nullable()->comment('客户备注');
            $table->text('internal_note')->nullable()->comment('内部备注');
            $table->timestamp('confirmed_at')->nullable()->comment('确认时间');
            $table->timestamp('shipped_at')->nullable()->comment('发货时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->index('order_no');
            $table->index(['status', 'created_at']);
            $table->index(['supplier_id', 'status']);
            $table->index(['customer_phone']);
            $table->index(['province', 'city']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moq_orders');
    }
};
