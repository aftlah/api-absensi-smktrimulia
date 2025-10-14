<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AktivitasTerbaru extends Model
{
    use HasFactory;

    protected $table = 'aktivitas_terbaru';
    protected $primaryKey = 'aktivitas_id';
    protected $fillable = ['akun_id', 'aksi', 'deskripsi', 'ikon'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
    }
}
