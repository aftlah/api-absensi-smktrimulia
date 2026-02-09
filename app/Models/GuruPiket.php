<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuruPiket extends Model
{
    use HasFactory;

    protected $table = 'guru_piket';
    protected $primaryKey = 'gurket_id';
    protected $fillable = ['username', 'nama', 'akun_id'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
    }

    public function jadwalPiket()
    {
        return $this->hasMany(JadwalPiket::class, 'gurket_id', 'gurket_id');
    }
}
