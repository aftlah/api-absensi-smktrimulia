<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    /** @use HasFactory<\Database\Factories\SiswaFactory> */
    use HasFactory;

    protected $table = 'siswa';
    protected $primaryKey = 'siswa_id';
    protected $fillable = ['nis', 'nama', 'akun_id', 'kelas_id', 'jenkel'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'kelas_id');
    }

    public function absensi()
    {
        return $this->hasMany(Absensi::class, 'siswa_id', 'siswa_id');
    }

    // riwayat kelas
    public function riwayatKelas()
    {
        return $this->hasMany(RiwayatKelas::class, 'siswa_id', 'siswa_id');
    }


    public function rencanaAbsensi()
    {
        return $this->hasMany(RencanaAbsensi::class, 'siswa_id', 'siswa_id');
    }
}