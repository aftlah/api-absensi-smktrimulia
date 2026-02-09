<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaliKelas extends Model
{
    use HasFactory;

    protected $table = 'wali_kelas';
    protected $primaryKey = 'walas_id';
    protected $fillable = ['username', 'nama', 'akun_id'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
    }
    
    // kelas
    public function kelas()
    {
        return $this->hasOne(Kelas::class, 'walas_id', 'walas_id');
    }
}