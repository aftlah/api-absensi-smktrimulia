<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RencanaAbsensi extends Model
{
    use HasFactory;

    protected $table = 'rencana_absensi';
    protected $primaryKey = 'rensi_id';
    protected $fillable = ['tanggal', 'status_hari', 'keterangan', 'kelas_id'];

    // kelas
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'kelas_id');
    }
}