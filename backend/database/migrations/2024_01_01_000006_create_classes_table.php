<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('school_id')->nullable()->constrained('schools')->onDelete('set null');
            $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('set null');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('head_teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name', 200);
            $table->string('code', 50)->unique();
            $table->integer('max_students')->default(50);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('class_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('enrolled_at')->nullable();
            $table->string('status', 50)->default('enrolled');
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_student');
        Schema::dropIfExists('classes');
    }
};
