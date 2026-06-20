<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'course_id',
        'teacher_id',
        'head_teacher_id',
        'name',
        'code',
        'max_students',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'max_students' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function headTeacher()
    {
        return $this->belongsTo(User::class, 'head_teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'class_student', 'class_id', 'student_id');
    }
}
