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
    protected $fillable = ['nis', 'nama', 'akun_id', 'jenkel'];
    protected $appends = ['kelas'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
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

    public function getKelasAttribute()
    {
        $riwayat = $this->riwayatKelas;

        if ($riwayat->isEmpty()) {
            return null;
        }

        $aktif = $riwayat->firstWhere('status', 'aktif');

        if ($aktif) {
            return $aktif->kelas;
        }

        $latest = $riwayat->sortByDesc('created_at')->first();

        return $latest ? $latest->kelas : null;
    }


    public function rencanaAbsensi()
    {
        return $this->hasMany(RencanaAbsensi::class, 'siswa_id', 'siswa_id');
    }
}
