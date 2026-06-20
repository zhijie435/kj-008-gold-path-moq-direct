<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'contact_phone',
        'contact_email',
        'is_active',
        'expired_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expired_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function schools()
    {
        return $this->hasMany(School::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
