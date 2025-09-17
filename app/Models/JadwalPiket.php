<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalPiket extends Model
{
    use HasFactory;

    protected $table = 'jadwal_piket';
    protected $primaryKey = 'jad_piket_id';
    protected $fillable = ['tanggal', 'gurket_id'];

    public function guruPiket()
    {
        return $this->belongsTo(GuruPiket::class, 'gurket_id', 'gurket_id');
    }
}
