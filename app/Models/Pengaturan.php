<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    use HasFactory;

    protected $table = 'pengaturan';
    protected $primaryKey = 'pengaturan_id';
    protected $fillable = [
        'latitude',
        'longitude',
        'radius_meter',
        'jam_masuk',
        'jam_pulang',
        'toleransi_telat'
    ];
}
