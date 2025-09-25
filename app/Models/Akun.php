<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Akun extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $table = 'akun';
    protected $primaryKey = 'akun_id';
    protected $fillable = ['username', 'password', 'role'];

    protected $hidden = ['password'];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function siswa()
    {
        return $this->hasOne(Siswa::class, 'akun_id', 'akun_id');
    }

    public function guruPiket()
    {
        return $this->hasOne(GuruPiket::class, 'akun_id', 'akun_id');
    }

    public function waliKelas()
    {
        return $this->hasOne(WaliKelas::class, 'akun_id', 'akun_id');
    }

    public function admin()
    {
        return $this->hasOne(Admin::class, 'akun_id', 'akun_id');
    }
}
