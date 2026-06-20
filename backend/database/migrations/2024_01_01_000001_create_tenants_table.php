<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
