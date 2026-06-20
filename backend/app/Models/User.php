<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'is_active',
        'tenant_id',
        'school_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_OPERATOR = 'operator';
    const ROLE_VIEWER = 'viewer';

    public static function getRoleOptions(): array
    {
        return [
            ['value' => self::ROLE_ADMIN, 'label' => '管理员'],
            ['value' => self::ROLE_OPERATOR, 'label' => '运营人员'],
            ['value' => self::ROLE_VIEWER, 'label' => '只读用户'],
        ];
    }
}
