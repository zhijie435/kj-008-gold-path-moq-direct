<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('商品名称');
            $table->string('sku', 50)->unique()->comment('SKU编码');
            $table->string('barcode', 50)->nullable()->comment('条形码');
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('供应商ID');
            $table->string('category', 100)->nullable()->comment('分类');
            $table->string('brand', 100)->nullable()->comment('品牌');
            $table->string('specification', 200)->nullable()->comment('规格');
            $table->string('unit', 20)->default('件')->comment('单位');
            $table->integer('moq')->default(1)->comment('最小起订量');
            $table->decimal('price', 10, 2)->default(0)->comment('销售价');
            $table->decimal('cost_price', 10, 2)->default(0)->comment('成本价');
            $table->decimal('weight', 10, 2)->default(0)->comment('重量(kg)');
            $table->decimal('volume', 10, 2)->default(0)->comment('体积(m³)');
            $table->string('origin', 100)->nullable()->comment('产地');
            $table->text('description')->nullable()->comment('描述');
            $table->json('images')->nullable()->comment('图片');
            $table->json('attributes')->nullable()->comment('属性');
            $table->integer('stock_quantity')->default(0)->comment('库存数量');
            $table->integer('safety_stock')->default(0)->comment('安全库存');
            $table->boolean('is_active')->default(true)->comment('是否上架');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->index('sku');
            $table->index('barcode');
            $table->index(['supplier_id', 'is_active']);
            $table->index(['category', 'is_active']);
            $table->index('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
