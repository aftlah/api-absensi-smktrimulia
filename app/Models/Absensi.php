<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    use HasFactory;

    protected $table = 'absensi';
    protected $primaryKey = 'absensi_id';
    protected $fillable = [
        'siswa_id',
        'tanggal',
        'jam_datang',
        'jam_pulang',
        'status',
        'latitude',
        'longitude',
        'bukti',
        'jenis_absen'
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'siswa_id');
    }
}
