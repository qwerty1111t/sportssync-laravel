<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'password_hash',
        'role',
        'status',
        'display_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? trim(strtolower($value)) : $value
        );
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? trim(strtolower($value)) : $value
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? trim(strtolower($value)) : $value
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
