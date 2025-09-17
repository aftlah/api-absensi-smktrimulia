<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaliKelas extends Model
{
    use HasFactory;

    protected $table = 'wali_kelas';
    protected $primaryKey = 'walas_id';
    protected $fillable = ['nip', 'nama', 'akun_id', 'kelas_id'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'kelas_id');
    }
}
