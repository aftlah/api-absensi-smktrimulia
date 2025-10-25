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
        'rensi_id',
        'jam_datang',
        'jam_pulang',
        'latitude_datang',
        'longitude_datang',
        'latitude_pulang',
        'longitude_pulang',
        'status',
        'is_verif',
        'bukti',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'siswa_id');
    }
}