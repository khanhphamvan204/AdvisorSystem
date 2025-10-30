<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements Authenticatable, JWTSubject
{
    use \Illuminate\Auth\Authenticatable;

    protected $primaryKey = 'user_id';
    protected $fillable = [
        'user_code',
        'full_name',
        'email',
        'password_hash',
        'phone_number',
        'avatar_url',
        'role',
        'last_login'
    ];

    protected $hidden = ['password_hash'];

    // JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return ['role' => $this->role];
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }
    public function advisor()
    {
        return $this->hasOne(Advisor::class, 'user_id');
    }
}