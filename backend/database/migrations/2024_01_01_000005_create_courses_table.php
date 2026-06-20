<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('school_id')->nullable()->constrained('schools')->onDelete('set null');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name', 200);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('credit', 4, 1)->nullable();
            $table->integer('hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['school_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
