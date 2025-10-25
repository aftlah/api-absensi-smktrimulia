<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatKelas extends Model
{
    use HasFactory;

    protected $table = 'riwayat_kelas';
    protected $primaryKey = 'riwayat_kelas_id';
    protected $fillable = ['siswa_id','kelas_id','status'];

    // siswa
    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'siswa_id');
    }

    // kelas
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'kelas_id');
    }
}