<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AktivitasTerbaru extends Model
{
    use HasFactory;

    protected $table = 'aktivitas_terbaru';
    protected $primaryKey = 'id'; // Changed from 'aktivitas_id' to 'id'
    protected $fillable = ['akun_id', 'tabel', 'aksi', 'deskripsi', 'user', 'role'];

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'akun_id');
    }
}