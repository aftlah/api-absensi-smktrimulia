<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    /** @use HasFactory<\Database\Factories\KelasFactory> */
    use HasFactory;

    protected $table = 'kelas';
    protected $primaryKey = 'kelas_id';
    protected $fillable = ['tingkat', 'paralel', 'jurusan_id', 'walas_id']; //waw

    public function siswa()
    {
        return $this->hasMany(Siswa::class, 'kelas_id', 'kelas_id');
    }

    
    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'jurusan_id');
    }

    public function riwayatKelas()
    {
        return $this->hasMany(RiwayatKelas::class, 'kelas_id', 'kelas_id');
    }

    // wali kelas
    public function walas()
    {
        return $this->belongsTo(WaliKelas::class, 'walas_id', 'walas_id');
    }


    // rencana_absensi
    public function rencanaAbsensi()
    {
        return $this->hasMany(RencanaAbsensi::class, 'kelas_id', 'kelas_id');
    }
}
