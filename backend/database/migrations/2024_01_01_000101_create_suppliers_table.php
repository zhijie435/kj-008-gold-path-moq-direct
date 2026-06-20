<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('供应商名称');
            $table->string('code', 50)->unique()->comment('供应商编码');
            $table->string('contact_person', 50)->nullable()->comment('联系人');
            $table->string('phone', 20)->nullable()->comment('联系电话');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->string('province', 50)->nullable()->comment('省份');
            $table->string('city', 50)->nullable()->comment('城市');
            $table->string('district', 50)->nullable()->comment('区县');
            $table->string('address', 500)->nullable()->comment('详细地址');
            $table->string('business_license', 100)->nullable()->comment('营业执照号');
            $table->string('bank_name', 100)->nullable()->comment('开户银行');
            $table->string('bank_account', 50)->nullable()->comment('银行账号');
            $table->string('tax_number', 50)->nullable()->comment('税号');
            $table->text('remark')->nullable()->comment('备注');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('is_active');
            $table->index(['province', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
